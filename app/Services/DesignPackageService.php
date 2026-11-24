<?php

namespace App\Services;

use App\Enums\{DesignPackageStatus, DesignVersionDecision};
use App\Models\{DesignPackageVersion, Document, DocumentVersion, Party, Project, ProjectDesignPackage, User};
use App\Notifications\ProjectWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Design packages follow the drawings an engineering office sends: each upload
 * is a numbered version, and the latest one is approved or rejected. The package
 * status is always derived from that latest version.
 */
class DesignPackageService
{
    public function createPackage(User $actor, Project $project, array $data): ProjectDesignPackage
    {
        $this->authorize($actor, 'update', $project);
        $this->authorize($actor, 'create', ProjectDesignPackage::class);
        $this->assertProjectOpen($project);

        $package = new ProjectDesignPackage([
            ...$this->packageAttributes($project->company_id, $data),
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'created_by' => $actor->id,
        ]);
        $package->forceFill(['status' => DesignPackageStatus::NoDrawings])->save();

        return $package->refresh();
    }

    public function updatePackage(User $actor, ProjectDesignPackage $package, array $data): ProjectDesignPackage
    {
        $this->authorize($actor, 'update', $package);
        $this->assertProjectOpen($package->project);
        $package->update($this->packageAttributes($package->company_id, $data));

        return $package->refresh();
    }

    /** @param array{file_path: string, file_name?: ?string, notes?: ?string} $data */
    public function uploadVersion(User $actor, ProjectDesignPackage $package, array $data): DesignPackageVersion
    {
        $this->authorize($actor, 'uploadVersion', $package);
        if (blank($data['file_path'] ?? null)) {
            $this->invalid('file_path', 'file_required');
        }

        return DB::transaction(function () use ($actor, $package, $data): DesignPackageVersion {
            $locked = ProjectDesignPackage::withoutGlobalScopes()->lockForUpdate()->findOrFail($package->id);
            $this->assertProjectOpen($locked->project);
            $number = ((int) DesignPackageVersion::withoutGlobalScopes()->where('project_design_package_id', $locked->id)->max('version_number')) + 1;

            // A corrected file that arrives before a decision replaces the one still waiting.
            DesignPackageVersion::withoutGlobalScopes()
                ->where('project_design_package_id', $locked->id)
                ->where('decision', DesignVersionDecision::Pending->value)
                ->update(['decision' => DesignVersionDecision::Superseded->value]);

            $document = Document::firstOrCreate(
                ['documentable_type' => ProjectDesignPackage::class, 'documentable_id' => $locked->id],
                ['title' => $locked->name, 'created_by' => $actor->id],
            );
            $file = DocumentVersion::create([
                'company_id' => $locked->company_id,
                'document_id' => $document->id,
                'version_number' => ((int) $document->versions()->max('version_number')) + 1,
                'file_name' => filled($data['file_name'] ?? null) ? $data['file_name'] : basename((string) $data['file_path']),
                'file_path' => $data['file_path'],
                'notes' => $data['notes'] ?? null,
                'uploaded_by' => $actor->id,
            ]);

            $version = new DesignPackageVersion([
                'company_id' => $locked->company_id,
                'project_design_package_id' => $locked->id,
                'version_number' => $number,
                'document_version_id' => $file->id,
                'notes' => $data['notes'] ?? null,
                'uploaded_by' => $actor->id,
            ]);
            $version->forceFill(['decision' => DesignVersionDecision::Pending])->save();
            $locked->forceFill(['status' => DesignPackageStatus::AwaitingDecision])->save();

            DB::afterCommit(fn () => $this->notify($actor, $locked, 'uploaded', ['package' => $locked->name, 'version' => $number]));

            return $version->refresh();
        });
    }

    public function approve(User $actor, DesignPackageVersion $version, ?string $comment = null): DesignPackageVersion
    {
        return $this->decide($actor, $version, DesignVersionDecision::Approved, $comment);
    }

    public function reject(User $actor, DesignPackageVersion $version, ?string $comment): DesignPackageVersion
    {
        if (blank(trim((string) $comment))) {
            $this->invalid('comment', 'comment_required');
        }

        return $this->decide($actor, $version, DesignVersionDecision::Rejected, $comment);
    }

    private function decide(User $actor, DesignPackageVersion $version, DesignVersionDecision $decision, ?string $comment): DesignPackageVersion
    {
        $package = ProjectDesignPackage::withoutGlobalScopes()->findOrFail($version->project_design_package_id);
        $this->authorize($actor, 'decideVersion', $package);

        return DB::transaction(function () use ($actor, $version, $decision, $comment): DesignPackageVersion {
            $package = ProjectDesignPackage::withoutGlobalScopes()->lockForUpdate()->findOrFail($version->project_design_package_id);
            $this->assertProjectOpen($package->project);
            $locked = DesignPackageVersion::withoutGlobalScopes()->lockForUpdate()->findOrFail($version->id);
            $latest = (int) DesignPackageVersion::withoutGlobalScopes()->where('project_design_package_id', $package->id)->max('version_number');
            if ($locked->version_number !== $latest) {
                $this->invalid('version', 'only_latest');
            }
            if ($locked->decision !== DesignVersionDecision::Pending) {
                $this->invalid('version', 'already_decided');
            }

            $locked->forceFill([
                'decision' => $decision,
                'decision_comment' => filled(trim((string) $comment)) ? trim((string) $comment) : null,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();
            $package->forceFill([
                'status' => $decision === DesignVersionDecision::Approved ? DesignPackageStatus::Approved : DesignPackageStatus::ChangesRequested,
            ])->save();

            DB::afterCommit(fn () => $this->notify($actor, $package, $decision->value, ['package' => $package->name, 'version' => $locked->version_number]));

            return $locked->refresh();
        });
    }

    private function packageAttributes(int $companyId, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $this->invalid('name', 'name_required');
        }
        $discipline = $data['discipline'] ?? null;
        if (filled($discipline) && ! in_array($discipline, ProjectDesignPackage::DISCIPLINES, true)) {
            $this->invalid('discipline', 'discipline_invalid');
        }
        $officeId = $data['engineering_office_id'] ?? null;
        if (filled($officeId)) {
            $office = Party::withoutGlobalScopes()->find($officeId);
            if (! $office || (int) $office->company_id !== $companyId) {
                $this->invalid('engineering_office_id', 'office_invalid');
            }
        }

        return [
            'name' => $name,
            'discipline' => filled($discipline) ? $discipline : null,
            'engineering_office_id' => filled($officeId) ? (int) $officeId : null,
            'target_date' => filled($data['target_date'] ?? null) ? $data['target_date'] : null,
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
        ];
    }

    private function assertProjectOpen(Project $project): void
    {
        if (in_array($project->status, ['closed', 'cancelled'], true)) {
            $this->invalid('project', 'project_closed');
        }
    }

    /** Each recipient reads the notification in their own language. */
    private function notify(User $actor, ProjectDesignPackage $package, string $event, array $params): void
    {
        $package->project->activeMembers()->with('user')->get()->pluck('user')->filter()->push($actor)->unique('id')
            ->each(function (User $user) use ($package, $event, $params): void {
                $locale = $user->locale ?: app()->getLocale();
                $user->notify(new ProjectWorkflowNotification(
                    __("project.design.notify.{$event}_title", $params, $locale),
                    __("project.design.notify.{$event}_body", $params, $locale),
                    $package->project_id,
                    \App\Support\NotificationLink::to($package, $package->project),
                ));
            });
    }

    private function authorize(User $user, string $ability, object|string $record): void
    {
        if (! $user->can($ability, $record)) {
            throw new AuthorizationException;
        }
    }

    private function invalid(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('project.design.errors.'.$key)]);
    }
}
