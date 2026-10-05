<?php

namespace App\Services;

use App\Models\{DesignPackageActivity, DesignPackageApproval, DesignPackageAssignment, DesignPackageReview, DesignPackageRevision, DesignPackageScopeItem, DesignPackageSubmission, DesignPackageSubmissionDocument, DesignReviewFinding, DesignRevisionFinding, Document, DocumentVersion, Party, Project, ProjectDesignPackage, User};
use App\Notifications\ProjectWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesignEngineeringService
{
    public function createPackage(User $actor, Project $project, array $attributes): ProjectDesignPackage
    {
        $this->authorize($actor, 'update', $project);
        $this->authorize($actor, 'create', new ProjectDesignPackage(['company_id' => $project->company_id]));
        $this->assertProjectAvailable($project);
        $package = ProjectDesignPackage::create(array_merge(
            collect($attributes)->only(['name', 'code', 'discipline', 'description', 'target_submission_date'])->all(),
            ['company_id' => $project->company_id, 'project_id' => $project->id, 'created_by' => $actor->id]
        ));
        $package->forceFill(['status' => 'planned'])->saveOrFail();
        return $package->refresh();
    }

    public function updatePackage(User $actor, ProjectDesignPackage $package, array $attributes): ProjectDesignPackage
    {
        $this->authorize($actor, 'update', $package);
        $this->assertStructurallyMutable($package);
        $package->update(collect($attributes)->only(['name', 'code', 'discipline', 'description', 'target_submission_date'])->all());
        return $package->refresh();
    }

    public function assignOffice(User $actor, ProjectDesignPackage $package, Party $office, array $attributes = []): DesignPackageAssignment
    {
        $this->authorize($actor, 'assignOffice', $package);
        $this->assertWorkflowState($package, ['planned', 'assigned', 'in_progress', 'revision_required']);
        $this->sameCompany($package, $office);
        if (!$office->is_active)
            throw ValidationException::withMessages(['party_id' => 'The engineering office must be active.']);
        return DB::transaction(function () use ($actor, $package, $office, $attributes) {
            if ($current = $package->activeAssignment()->first()) {
                $current->forceFill(['status' => 'replaced', 'ended_at' => now()])->save();
            }
            $assignment = DesignPackageAssignment::create([
                'company_id' => $package->company_id,
                'project_design_package_id' => $package->id,
                'party_id' => $office->id,
                'assigned_at' => $attributes['assigned_at'] ?? now(),
                'notes' => $attributes['notes'] ?? null,
                'assigned_by' => $actor->id,
            ]);
            $assignment->forceFill(['status' => 'active'])->save();
            if ($package->status === 'planned')
                $this->setPackageStatus($package, 'assigned');
            DB::afterCommit(fn() => $this->notify($actor, $package, 'Engineering office assigned', "{$office->name} is assigned to {$package->name}."));
            return $assignment->refresh();
        });
    }

    public function addScopeItem(User $actor, DesignPackageAssignment $assignment, array $attributes): DesignPackageScopeItem
    {
        $this->authorize($actor, 'create', new DesignPackageScopeItem(['company_id' => $assignment->company_id]));
        $this->assertAssignmentActive($assignment);
        $this->assertStructurallyMutable($assignment->package);
        $item = DesignPackageScopeItem::create(array_merge(
            collect($attributes)->only(['code', 'title', 'description', 'sort_order', 'target_date'])->all(),
            ['company_id' => $assignment->company_id, 'design_package_assignment_id' => $assignment->id]
        ));
        $item->forceFill(['status' => 'pending'])->save();
        return $item;
    }

    public function transitionScopeItem(User $actor, DesignPackageScopeItem $item, string $to): DesignPackageScopeItem
    {
        $this->authorize($actor, 'update', $item);
        $allowed = ['pending' => ['in_progress', 'cancelled'], 'in_progress' => ['ready', 'cancelled']];
        if (!in_array($to, $allowed[$item->status] ?? [], true))
            $this->invalidTransition('scope item', $item->status, $to);
        $item->forceFill(['status' => $to, 'completed_at' => $to === 'ready' ? now() : null])->save();
        $package = $item->assignment->package;
        if ($to === 'in_progress' && $package->status === 'assigned')
            $this->setPackageStatus($package, 'in_progress');
        if ($to === 'ready' && !$item->assignment->scopeItems()->whereNotIn('status', ['ready', 'cancelled'])->exists()) {
            DB::afterCommit(fn() => $this->notify($actor, $package, 'Design scope is ready', "All scope items for {$package->name} are ready for submission."));
        }
        return $item->refresh();
    }

    public function addActivity(User $actor, ProjectDesignPackage $package, array $attributes): DesignPackageActivity
    {
        $this->authorize($actor, 'create', new DesignPackageActivity(['company_id' => $package->company_id]));
        $this->assertStructurallyMutable($package);
        if ($scopeId = $attributes['design_package_scope_item_id'] ?? null) {
            $scope = DesignPackageScopeItem::withoutGlobalScopes()->findOrFail($scopeId);
            if ((int) $scope->assignment->project_design_package_id !== (int) $package->id)
                throw ValidationException::withMessages(['design_package_scope_item_id' => 'The selected scope item does not belong to this package.']);
        }
        return DesignPackageActivity::create(array_merge(collect($attributes)->only(['design_package_scope_item_id', 'type', 'description', 'occurred_at', 'metadata'])->all(), ['company_id' => $package->company_id, 'project_design_package_id' => $package->id, 'performed_by' => $actor->id, 'occurred_at' => $attributes['occurred_at'] ?? now()]));
    }

    public function addDocumentVersion(User $actor, ProjectDesignPackage $package, array $attributes, ?Document $document = null): DocumentVersion
    {
        $this->authorize($actor, 'create', DocumentVersion::class);
        $this->assertDocumentWorkAllowed($package);
        return DB::transaction(function () use ($actor, $package, $attributes, $document) {
            $fileName = $attributes['file_name'] ?? basename((string) $attributes['file_path']);
            if ($document) {
                if ($document->documentable_type !== ProjectDesignPackage::class || (int) $document->documentable_id !== (int) $package->id)
                    throw ValidationException::withMessages(['document_id' => 'The selected document does not belong to this design package.']);
            } else {
                $document = Document::create([
                    'documentable_type' => ProjectDesignPackage::class,
                    'documentable_id' => $package->id,
                    'title' => $attributes['title'],
                    'description' => $attributes['document_description'] ?? null,
                    'created_by' => $actor->id,
                ]);
            }
            $number = ((int) $document->versions()->max('version_number')) + 1;
            return DocumentVersion::create([
                'company_id' => $package->company_id,
                'document_id' => $document->id,
                'version_number' => $number,
                'file_name' => $fileName,
                'file_path' => $attributes['file_path'],
                'mime_type' => $attributes['mime_type'] ?? null,
                'file_size' => $attributes['file_size'] ?? null,
                'checksum' => $attributes['checksum'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'uploaded_by' => $actor->id,
            ]);
        });
    }

    public function submit(User $actor, ProjectDesignPackage $package, array $documentVersionIds, ?string $notes = null, ?DesignPackageRevision $revision = null): DesignPackageSubmission
    {
        $this->authorize($actor, 'submit', $package);
        $this->assertWorkflowState($package, ['assigned', 'in_progress', 'revision_required']);
        $assignment = $package->activeAssignment()->first();
        if (!$assignment)
            throw ValidationException::withMessages(['assignment' => 'Assign an engineering office before submitting this package.']);
        if (!$assignment->scopeItems()->exists() || $assignment->scopeItems()->whereNotIn('status', ['ready', 'cancelled'])->exists())
            throw ValidationException::withMessages(['scope' => 'All active scope items must be ready before formal submission.']);
        if ($revision && ($revision->project_design_package_id !== $package->id || $revision->status !== 'ready'))
            throw ValidationException::withMessages(['revision' => 'Only a ready revision for this package can be resubmitted.']);
        $versions = DocumentVersion::withoutGlobalScopes()->whereIn('id', $documentVersionIds)->get();
        if ($versions->count() !== count(array_unique($documentVersionIds)) || $versions->isEmpty())
            throw ValidationException::withMessages(['document_versions' => 'Attach at least one accessible document version.']);
        foreach ($versions as $version)
            $this->assertVersionBelongsToPackage($version, $package);
        return DB::transaction(function () use ($actor, $package, $assignment, $versions, $notes, $revision) {
            $submission = DesignPackageSubmission::create([
                'company_id' => $package->company_id,
                'project_design_package_id' => $package->id,
                'design_package_assignment_id' => $assignment->id,
                'submission_number' => ((int) $package->submissions()->max('submission_number')) + 1,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
                'notes' => $notes,
            ]);
            $submission->forceFill(['status' => 'submitted'])->save();
            foreach ($versions as $version)
                DesignPackageSubmissionDocument::create(['company_id' => $package->company_id, 'design_package_submission_id' => $submission->id, 'document_version_id' => $version->id]);
            if ($revision)
                $revision->forceFill(['status' => 'submitted'])->save();
            $this->setPackageStatus($package, $revision ? 'resubmitted' : 'submitted');
            DB::afterCommit(fn() => $this->notify($actor, $package, 'Formal design submission created', "Submission #{$submission->submission_number} for {$package->name} is ready for review."));
            return $submission->refresh();
        });
    }

    public function startReview(User $actor, DesignPackageSubmission $submission, User $reviewer): DesignPackageReview
    {
        $this->authorize($actor, 'review', $submission);
        $package = $submission->package;
        if ($submission->status !== 'submitted' || !in_array($package->status, ['submitted', 'resubmitted'], true))
            $this->invalidTransition('submission', $submission->status, 'under_review');
        $this->sameCompany($package, $reviewer);
        return DB::transaction(function () use ($actor, $submission, $reviewer, $package) {
            $review = DesignPackageReview::create(['company_id' => $package->company_id, 'design_package_submission_id' => $submission->id, 'reviewer_id' => $reviewer->id]);
            $review->forceFill(['status' => 'in_review', 'started_at' => now()])->save();
            $submission->forceFill(['status' => 'under_review'])->save();
            $this->setPackageStatus($package, 'under_review');
            DB::afterCommit(fn() => $reviewer->notify(new ProjectWorkflowNotification('Design review assigned', "Review submission #{$submission->submission_number} for {$package->name}.", $package->project_id)));
            return $review->refresh();
        });
    }

    public function createFinding(User $actor, DesignPackageReview $review, array $attributes): DesignReviewFinding
    {
        $this->authorize($actor, 'createFinding', $review);
        if ($review->status !== 'in_review')
            throw ValidationException::withMessages(['review' => 'Findings may be recorded only while the review is in progress.']);
        if ((int) $review->reviewer_id !== (int) $actor->id && !$actor->isSuperAdmin())
            throw new AuthorizationException;
        $package = $review->submission->package;
        $severity = $attributes['severity'] ?? null;
        if (!in_array($severity, DesignReviewFinding::SEVERITIES, true))
            throw ValidationException::withMessages(['severity' => 'Select a valid finding severity.']);
        foreach (['design_package_scope_item_id' => DesignPackageScopeItem::class, 'document_version_id' => DocumentVersion::class] as $key => $model) {
            if (!empty($attributes[$key])) {
                $related = $model::withoutGlobalScopes()->findOrFail($attributes[$key]);
                if ($key === 'design_package_scope_item_id' && (int) $related->assignment->project_design_package_id !== (int) $package->id)
                    throw ValidationException::withMessages([$key => 'The selected scope item is outside this package.']);
                if ($key === 'document_version_id')
                    $this->assertVersionAttachedToSubmission($related, $review->submission);
            }
        }
        $finding = DesignReviewFinding::create(array_merge(collect($attributes)->only(['design_package_scope_item_id', 'document_version_id', 'severity', 'title', 'description'])->all(), ['company_id' => $package->company_id, 'design_package_review_id' => $review->id]));
        $finding->forceFill(['status' => 'open'])->save();
        if (in_array($severity, ['major', 'critical'], true))
            DB::afterCommit(fn() => $this->notify($actor, $package, strtoupper($severity) . ' design finding', "{$finding->title} was raised for {$package->name}."));
        return $finding;
    }

    public function completeReview(User $actor, DesignPackageReview $review, ?string $summary = null): DesignPackageReview
    {
        $this->authorize($actor, 'review', $review->submission);
        if ($review->status !== 'in_review')
            $this->invalidTransition('review', $review->status, 'completed');
        if ((int) $review->reviewer_id !== (int) $actor->id && !$actor->isSuperAdmin())
            throw new AuthorizationException;
        return DB::transaction(function () use ($actor, $review, $summary) {
            $review->forceFill(['status' => 'completed', 'completed_at' => now(), 'summary' => $summary])->save();
            $submission = $review->submission;
            $submission->forceFill(['status' => 'reviewed'])->save();
            $package = $submission->package;
            if ($review->findings()->where('status', 'open')->exists())
                $this->setPackageStatus($package, 'revision_required');
            DB::afterCommit(fn() => $this->notify($actor, $package, 'Design review completed', "Submission #{$submission->submission_number} for {$package->name} was reviewed."));
            return $review->refresh();
        });
    }

    public function waiveFinding(User $actor, DesignReviewFinding $finding, string $notes): DesignReviewFinding
    {
        $this->authorize($actor, 'waive', $finding);
        if ($finding->status !== 'open')
            throw ValidationException::withMessages(['finding' => 'Only open findings may be waived.']);
        $finding->forceFill(['status' => 'waived', 'resolution_notes' => $notes, 'resolved_by' => $actor->id, 'resolved_at' => now()])->save();
        return $finding->refresh();
    }

    public function requestRevision(User $actor, DesignPackageSubmission $submission, array $findingIds, ?string $description = null): DesignPackageRevision
    {
        $this->authorize($actor, 'requestRevision', $submission);
        $package = $submission->package;
        if ($submission->status !== 'reviewed' || $package->status !== 'revision_required')
            throw ValidationException::withMessages(['submission' => 'A completed review with outstanding findings is required before requesting a revision.']);
        $findings = DesignReviewFinding::withoutGlobalScopes()->whereIn('id', $findingIds)->get();
        if ($findings->isEmpty() || $findings->count() !== count(array_unique($findingIds)))
            throw ValidationException::withMessages(['findings' => 'Select at least one valid finding.']);
        foreach ($findings as $finding)
            if ((int) $finding->review->design_package_submission_id !== (int) $submission->id || $finding->status !== 'open')
                throw ValidationException::withMessages(['findings' => 'Every selected finding must be open and belong to this submission.']);
        return DB::transaction(function () use ($actor, $package, $submission, $findings, $description) {
            if ($package->revisions()->whereIn('status', ['draft', 'in_progress', 'ready'])->exists())
                throw ValidationException::withMessages(['revision' => 'An active revision already exists for this package.']);
            $revision = DesignPackageRevision::create(['company_id' => $package->company_id, 'project_design_package_id' => $package->id, 'source_submission_id' => $submission->id, 'revision_number' => ((int) $package->revisions()->max('revision_number')) + 1, 'description' => $description, 'created_by' => $actor->id]);
            $revision->forceFill(['status' => 'draft'])->save();
            foreach ($findings as $finding)
                DesignRevisionFinding::create(['company_id' => $package->company_id, 'design_package_revision_id' => $revision->id, 'design_review_finding_id' => $finding->id]);
            DB::afterCommit(fn() => $this->notify($actor, $package, 'Design revision requested', "Revision #{$revision->revision_number} was created for {$package->name}."));
            return $revision->refresh();
        });
    }

    public function transitionRevision(User $actor, DesignPackageRevision $revision, string $to): DesignPackageRevision
    {
        $this->authorize($actor, 'submitRevision', $revision);
        $allowed = ['draft' => ['in_progress', 'cancelled'], 'in_progress' => ['ready', 'cancelled']];
        if (!in_array($to, $allowed[$revision->status] ?? [], true))
            $this->invalidTransition('revision', $revision->status, $to);
        $revision->forceFill(['status' => $to, 'started_at' => $to === 'in_progress' ? now() : $revision->started_at, 'ready_at' => $to === 'ready' ? now() : null])->save();
        return $revision->refresh();
    }

    public function approve(User $actor, ProjectDesignPackage $package, DesignPackageSubmission $submission, ?string $notes = null): DesignPackageApproval
    {
        $this->authorize($actor, 'approve', $package);
        if ($package->status !== 'under_review' || (int) $submission->project_design_package_id !== (int) $package->id || $submission->status !== 'reviewed')
            throw ValidationException::withMessages(['submission' => 'A reviewed submission for this package is required for approval.']);
        if (!$submission->reviews()->where('status', 'completed')->exists())
            throw ValidationException::withMessages(['review' => 'At least one completed review is required before approval.']);
        if (DesignReviewFinding::withoutGlobalScopes()->whereIn('design_package_review_id', $submission->reviews()->select('id'))->whereIn('severity', ['major', 'critical'])->whereIn('status', ['open', 'addressed'])->exists())
            throw ValidationException::withMessages(['findings' => 'Major or critical findings must be accepted or waived before approval.']);
        if (!$submission->documents()->exists())
            throw ValidationException::withMessages(['documents' => 'The approved submission must retain its exact document versions.']);
        return DB::transaction(function () use ($actor, $package, $submission, $notes) {
            $approval = DesignPackageApproval::create(['company_id' => $package->company_id, 'project_design_package_id' => $package->id, 'design_package_submission_id' => $submission->id, 'approved_by' => $actor->id, 'approved_at' => now(), 'notes' => $notes]);
            $submission->forceFill(['status' => 'approved'])->save();
            $this->setPackageStatus($package, 'approved', ['approved_at' => now()]);
            DB::afterCommit(fn() => $this->notify($actor, $package, 'Design package approved', "{$package->name} was approved from submission #{$submission->submission_number}."));
            return $approval;
        });
    }

    public function close(User $actor, ProjectDesignPackage $package): ProjectDesignPackage
    {
        $this->authorize($actor, 'close', $package);
        if ($package->status !== 'approved')
            $this->invalidTransition('package', $package->status, 'closed');
        $this->setPackageStatus($package, 'closed', ['closed_at' => now()]);
        DB::afterCommit(fn() => $this->notify($actor, $package, 'Design package closed', "{$package->name} is now closed."));
        return $package->refresh();
    }

    private function assertProjectAvailable(Project $project): void
    {
        if (in_array($project->status, ['closed', 'cancelled'], true))
            throw ValidationException::withMessages(['project' => 'Design packages cannot be changed on a closed or cancelled project.']);
    }
    private function assertStructurallyMutable(ProjectDesignPackage $package): void
    {
        $this->assertProjectAvailable($package->project);
        if (in_array($package->status, ['approved', 'closed'], true))
            throw ValidationException::withMessages(['package' => 'Approved and closed design packages preserve their formal history and cannot be structurally changed.']);
    }
    private function assertDocumentWorkAllowed(ProjectDesignPackage $package): void
    {
        $this->assertStructurallyMutable($package);
        if (!in_array($package->status, ['assigned', 'in_progress', 'revision_required'], true))
            throw ValidationException::withMessages(['status' => 'Document versions can be added only while work or revision is in progress.']);
    }
    private function assertAssignmentActive(DesignPackageAssignment $assignment): void
    {
        if ($assignment->status !== 'active')
            throw ValidationException::withMessages(['assignment' => 'Scope can be added only to the active office assignment.']);
    }
    private function assertWorkflowState(ProjectDesignPackage $package, array $states): void
    {
        $this->assertStructurallyMutable($package);
        if (!in_array($package->status, $states, true))
            throw ValidationException::withMessages(['status' => 'This action is not available while the package is ' . str_replace('_', ' ', $package->status) . '.']);
    }
    private function assertVersionBelongsToPackage(DocumentVersion $version, ProjectDesignPackage $package): void
    {
        $document = $version->document;
        if ((int) $version->company_id !== (int) $package->company_id || $document->documentable_type !== ProjectDesignPackage::class || (int) $document->documentable_id !== (int) $package->id)
            throw ValidationException::withMessages(['document_versions' => 'Every document version must belong to this package.']);
    }
    private function assertVersionAttachedToSubmission(DocumentVersion $version, DesignPackageSubmission $submission): void
    {
        if (!$submission->documents()->where('document_version_id', $version->id)->exists())
            throw ValidationException::withMessages(['document_version_id' => 'The finding must reference a version pinned to this submission.']);
    }
    private function sameCompany(object $left, object $right): void
    {
        if ((int) $left->company_id !== (int) $right->company_id)
            throw ValidationException::withMessages(['company_id' => 'Records from different companies cannot be associated.']);
    }
    private function authorize(User $user, string $ability, object|string $record): void
    {
        if (!$user->can($ability, $record))
            throw new AuthorizationException;
    }
    private function invalidTransition(string $name, string $from, string $to): never
    {
        throw ValidationException::withMessages(['status' => "Cannot transition {$name} from {$from} to {$to}."]);
    }
    private function setPackageStatus(ProjectDesignPackage $package, string $status, array $extra = []): void
    {
        $package->forceFill(array_merge(['status' => $status], $extra))->save();
    }
    private function notify(User $actor, ProjectDesignPackage $package, string $title, string $body): void
    {
        $package->project->activeMembers()->with('user')->get()->pluck('user')->filter()->push($actor)->unique('id')->each->notify(new ProjectWorkflowNotification($title, $body, $package->project_id));
    }
}
