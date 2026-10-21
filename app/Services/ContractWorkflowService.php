<?php

namespace App\Services;

use App\Enums\{
    ContractAmendmentStatus,
    ContractApprovalStatus,
    ContractStatus,
    ContractType,
    ContractVersionStatus,
};
use App\Models\{
    Contract,
    ContractAmendment,
    ContractApproval,
    ContractParty,
    ContractSignature,
    ContractStatusHistory,
    ContractTerm,
    ContractVersion,
    Document,
    DocumentVersion,
    ProjectPlannedUnit,
    Unit,
    User,
};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractWorkflowService
{
    public function submit(User $actor, ContractVersion $version): ContractVersion
    {
        $this->can($actor, 'submit_contract_version');

        return DB::transaction(function () use ($actor, $version): ContractVersion {
            $locked = $this->lockedVersion($version);
            $this->sameCompany($actor, $locked->company_id);
            if (! in_array($locked->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true)) {
                $this->invalid('version', __('Only a draft or returned version can be submitted.'));
            }
            if (! $locked->parties()->exists()) {
                $this->invalid('parties', __('Add at least one contract party before submission.'));
            }
            if (! $locked->terms()->exists()) {
                $this->invalid('terms', __('Add at least one contract term before submission.'));
            }
            $this->assertVersionDates($locked);

            $contract = $locked->contract;
            $from = $contract->status->value;
            $targetStatus = $this->contractWorkflowStatus($contract, $locked, ContractStatus::UnderReview);
            $locked->update(['status' => ContractVersionStatus::Submitted, 'submitted_at' => now()]);
            $contract->update(['status' => $targetStatus]);
            $this->history($contract, $locked, $actor, $from, $targetStatus->value, 'submitted_for_review');

            return $locked->refresh();
        });
    }

    public function approve(User $actor, ContractVersion $version, ?string $notes = null): ContractApproval
    {
        $this->can($actor, 'approve_contract_version');

        return DB::transaction(function () use ($actor, $version, $notes): ContractApproval {
            $locked = $this->lockedVersion($version);
            $this->sameCompany($actor, $locked->company_id);
            if ($locked->status !== ContractVersionStatus::Submitted) {
                $this->invalid('version', __('Only a submitted version can be approved.'));
            }

            $approval = ContractApproval::withoutGlobalScopes()->updateOrCreate(
                ['contract_version_id' => $locked->id, 'reviewed_by' => $actor->id],
                [
                    'company_id' => $locked->company_id,
                    'status' => ContractApprovalStatus::Approved,
                    'notes' => $notes,
                    'reviewed_at' => now(),
                ],
            );
            $contract = $locked->contract;
            $from = $contract->status->value;
            $targetStatus = $this->contractWorkflowStatus($contract, $locked, ContractStatus::Approved);
            $locked->update(['status' => ContractVersionStatus::Approved, 'approved_at' => now()]);
            $contract->update(['status' => $targetStatus]);
            $this->history($contract, $locked, $actor, $from, $targetStatus->value, 'approved');

            return $approval->refresh();
        });
    }

    public function requestRevision(User $actor, ContractVersion $version, string $notes): ContractApproval
    {
        $this->can($actor, 'request_contract_revision');

        return DB::transaction(function () use ($actor, $version, $notes): ContractApproval {
            $locked = $this->lockedVersion($version);
            $this->sameCompany($actor, $locked->company_id);
            if ($locked->status !== ContractVersionStatus::Submitted) {
                $this->invalid('version', __('Only a submitted version can be returned for revision.'));
            }

            $contract = $locked->contract;
            $from = $contract->status->value;
            $targetStatus = $this->contractWorkflowStatus($contract, $locked, ContractStatus::RevisionRequired);
            $locked->update(['status' => ContractVersionStatus::RevisionRequired]);
            $contract->update(['status' => $targetStatus]);
            $approval = ContractApproval::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'contract_version_id' => $locked->id,
                'reviewed_by' => $actor->id,
                'status' => ContractApprovalStatus::RevisionRequired,
                'notes' => $notes,
                'reviewed_at' => now(),
            ]);
            $this->history($contract, $locked, $actor, $from, $targetStatus->value, 'revision_requested', $notes);

            return $approval;
        });
    }

    public function createRevision(User $actor, ContractVersion $source): ContractVersion
    {
        $this->can($actor, 'create_contract_revision');

        return DB::transaction(function () use ($actor, $source): ContractVersion {
            $locked = $this->lockedVersion($source, ['parties', 'terms']);
            $this->sameCompany($actor, $locked->company_id);
            if ($locked->status !== ContractVersionStatus::Approved) {
                $this->invalid('version', __('Only an approved version can be revised into a new legal version.'));
            }

            $draft = $this->cloneIntoDraft($actor, $locked);
            $locked->update(['status' => ContractVersionStatus::Superseded]);
            $contract = $locked->contract;
            $from = $contract->status->value;
            $targetStatus = $this->contractWorkflowStatus($contract, $locked, ContractStatus::Draft);
            $contract->update(['status' => $targetStatus]);
            $amendment = ContractAmendment::withoutGlobalScopes()
                ->where('resulting_contract_version_id', $locked->id)
                ->lockForUpdate()
                ->first();
            if ($amendment) {
                $amendment->update(['resulting_contract_version_id' => $draft->id]);
            }
            $this->history($contract, $draft, $actor, $from, $targetStatus->value, 'revision_created');

            return $draft->load(['parties.party', 'terms']);
        });
    }

    /** Attaches a working contract document only while its version is editable. */
    public function attachDocument(User $actor, ContractVersion $version, array $data): DocumentVersion
    {
        $this->can($actor, 'attach_contract_document');

        try {
            return DB::transaction(function () use ($actor, $version, $data): DocumentVersion {
                $locked = $this->lockedVersion($version);
                $this->sameCompany($actor, $locked->company_id);
                $this->assertDocumentsEditable($locked);
                $this->assertUploadedFile($data);

                $document = Document::create([
                    'documentable_type' => ContractVersion::class,
                    'documentable_id' => $locked->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'created_by' => $actor->id,
                ]);

                return $this->createDocumentVersion($locked, $document, $actor, $data);
            });
        } catch (\Throwable $exception) {
            $this->deleteUnreferencedFiles([$data['file_path'] ?? null]);

            throw $exception;
        }
    }

    /** Replaces a pre-approval working document without retaining stale draft files. */
    public function replaceDocument(User $actor, ContractVersion $version, Document $document, array $data): DocumentVersion
    {
        $this->can($actor, 'attach_contract_document');

        try {
            return DB::transaction(function () use ($actor, $version, $document, $data): DocumentVersion {
                $locked = $this->lockedVersion($version);
                $this->sameCompany($actor, $locked->company_id);
                $this->assertDocumentsEditable($locked);
                $this->assertUploadedFile($data);
                $lockedDocument = $this->lockedContractDocument($locked, $document);
                $oldPaths = $this->removeDocumentVersions($lockedDocument);

                $lockedDocument->update([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                ]);
                $replacement = $this->createDocumentVersion($locked, $lockedDocument, $actor, $data);

                DB::afterCommit(fn () => $this->deleteUnreferencedFiles($oldPaths));

                return $replacement;
            });
        } catch (\Throwable $exception) {
            $this->deleteUnreferencedFiles([$data['file_path'] ?? null]);

            throw $exception;
        }
    }

    /** Removes a working draft document and its file only when no other version uses it. */
    public function deleteDocument(User $actor, ContractVersion $version, Document $document): void
    {
        $this->can($actor, 'attach_contract_document');

        DB::transaction(function () use ($actor, $version, $document): void {
            $locked = $this->lockedVersion($version);
            $this->sameCompany($actor, $locked->company_id);
            $this->assertDocumentsEditable($locked);
            $lockedDocument = $this->lockedContractDocument($locked, $document);
            $paths = $this->removeDocumentVersions($lockedDocument);
            $lockedDocument->forceDelete();

            DB::afterCommit(fn () => $this->deleteUnreferencedFiles($paths));
        });
    }

    public function recordSignature(
        User $actor,
        ContractVersion $version,
        ContractParty $party,
        DocumentVersion $document,
        string $signatory,
        string $method,
        array $evidence = [],
        ?string $signatureImagePath = null,
    ): ContractSignature {
        $this->can($actor, 'record_contract_signature');

        return DB::transaction(function () use ($actor, $version, $party, $document, $signatory, $method, $evidence, $signatureImagePath): ContractSignature {
            $locked = $this->lockedVersion($version);
            $this->sameCompany($actor, $locked->company_id);
            if ($locked->status !== ContractVersionStatus::Approved) {
                $this->invalid('version', __('Only an approved version can be signed.'));
            }
            $party = ContractParty::withoutGlobalScopes()->lockForUpdate()->findOrFail($party->id);
            $document = DocumentVersion::withoutGlobalScopes()->with('document')->lockForUpdate()->findOrFail($document->id);
            if ($party->contract_version_id !== $locked->id
                || (int) $document->company_id !== (int) $locked->company_id
                || $document->document?->documentable_type !== ContractVersion::class
                || (int) $document->document?->documentable_id !== (int) $locked->id) {
                $this->invalid('signature', __('The party and document must belong to this exact contract version and company.'));
            }
            if (ContractSignature::withoutGlobalScopes()->where('contract_version_id', $locked->id)->where('contract_party_id', $party->id)->exists()) {
                $this->invalid('contract_party_id', __('A signature is already recorded for this contract party.'));
            }

            $stream = Storage::disk(config('filesystems.default'))->readStream($document->file_path);
            if (! is_resource($stream)) {
                $this->invalid('document_version_id', __('The signed document file cannot be read.'));
            }
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            fclose($stream);

            return ContractSignature::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'contract_version_id' => $locked->id,
                'contract_party_id' => $party->id,
                'document_version_id' => $document->id,
                'signatory_name' => $signatory,
                'signing_method' => $method,
                'signature_image_path' => $signatureImagePath,
                'file_sha256' => hash_final($context),
                'evidence' => $evidence,
                'signed_at' => now(),
                'recorded_by' => $actor->id,
            ]);
        });
    }

    public function activate(User $actor, Contract $contract, ContractVersion $version): Contract
    {
        $this->can($actor, 'activate_contract');

        return DB::transaction(function () use ($actor, $contract, $version): Contract {
            $lockedContract = Contract::withoutGlobalScopes()->lockForUpdate()->findOrFail($contract->id);
            $lockedVersion = $this->lockedVersion($version);
            $this->sameCompany($actor, $lockedContract->company_id);
            if ($lockedVersion->contract_id !== $lockedContract->id || $lockedVersion->status !== ContractVersionStatus::Approved) {
                $this->invalid('version', __('Activate an approved version of this contract only.'));
            }
            $this->assertAllPartiesSigned($lockedVersion);
            $this->assertAvailable($lockedContract, $lockedVersion);

            $from = $lockedContract->status->value;
            $lockedContract->update(['status' => ContractStatus::Active]);
            $lockedVersion->update(['status' => ContractVersionStatus::Signed]);
            ContractAmendment::withoutGlobalScopes()
                ->where('resulting_contract_version_id', $lockedVersion->id)
                ->where('status', ContractAmendmentStatus::Draft->value)
                ->update(['status' => ContractAmendmentStatus::Effective->value]);
            $this->history($lockedContract, $lockedVersion, $actor, $from, ContractStatus::Active->value, 'activated');

            return $lockedContract->refresh();
        });
    }

    public function createAmendment(
        User $actor,
        Contract $contract,
        ContractVersion $base,
        string $type,
        string $reason,
        ?string $date = null,
    ): ContractAmendment {
        $this->can($actor, 'create_contract_amendment');

        return DB::transaction(function () use ($actor, $contract, $base, $type, $reason, $date): ContractAmendment {
            $lockedContract = Contract::withoutGlobalScopes()->lockForUpdate()->findOrFail($contract->id);
            $lockedBase = $this->lockedVersion($base, ['parties', 'terms']);
            $this->sameCompany($actor, $lockedContract->company_id);
            if ($lockedContract->status !== ContractStatus::Active || $lockedBase->contract_id !== $lockedContract->id || $lockedBase->status !== ContractVersionStatus::Signed) {
                $this->invalid('contract', __('An amendment requires an active contract and signed version.'));
            }

            // A signed base remains immutable and legally retrievable. Its new
            // amendment draft is a separate version and cannot overwrite it.
            $draft = $this->cloneIntoDraft($actor, $lockedBase);
            $amendment = ContractAmendment::withoutGlobalScopes()->create([
                'company_id' => $lockedContract->company_id,
                'contract_id' => $lockedContract->id,
                'base_contract_version_id' => $lockedBase->id,
                'resulting_contract_version_id' => $draft->id,
                'amendment_type' => $type,
                'reason' => $reason,
                'effective_date' => $date,
                'created_by' => $actor->id,
            ]);
            $this->history($lockedContract, $draft, $actor, $lockedContract->status->value, $lockedContract->status->value, 'amendment_created', $reason);

            return $amendment->load('resultingVersion');
        });
    }

    private function cloneIntoDraft(User $actor, ContractVersion $source): ContractVersion
    {
        $contract = Contract::withoutGlobalScopes()->lockForUpdate()->findOrFail($source->contract_id);
        $next = ((int) $contract->versions()->max('version_number')) + 1;
        $draft = ContractVersion::withoutGlobalScopes()->create(array_merge(
            $source->only([
                'company_id', 'contract_id', 'template_version_id', 'total_amount', 'currency',
                'start_date', 'end_date', 'delivery_date', 'unit_snapshot', 'reservation_snapshot',
            ]),
            [
                'version_number' => $next,
                'status' => ContractVersionStatus::Draft,
                'created_by' => $actor->id,
                'submitted_at' => null,
                'approved_at' => null,
            ],
        ));
        foreach ($source->parties as $party) {
            ContractParty::withoutGlobalScopes()->create(array_merge(
                $party->only(['company_id', 'party_id', 'role', 'share_percentage', 'snapshot']),
                ['contract_version_id' => $draft->id],
            ));
        }
        foreach ($source->terms as $term) {
            ContractTerm::withoutGlobalScopes()->create(array_merge(
                $term->only(['company_id', 'term_type', 'position', 'title', 'text', 'metadata']),
                ['contract_version_id' => $draft->id, 'contract_party_id' => null],
            ));
        }

        return $draft;
    }

    private function assertDocumentsEditable(ContractVersion $version): void
    {
        if (! in_array($version->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true)) {
            $this->invalid('documents', __('Contract documents can only be managed while the version is a draft or requires revision.'));
        }
    }

    private function assertUploadedFile(array $data): void
    {
        if (empty($data['file_path'])) {
            $this->invalid('file_path', __('Upload the contract document before saving.'));
        }
    }

    private function lockedContractDocument(ContractVersion $version, Document $document): Document
    {
        $locked = Document::query()
            // The parent/version ownership is verified below; load all versions
            // while locked, including those hidden by the company global scope.
            ->with(['versions' => fn ($query) => $query->withoutGlobalScopes()])
            ->lockForUpdate()
            ->findOrFail($document->id);

        if ($locked->documentable_type !== ContractVersion::class || (int) $locked->documentable_id !== (int) $version->id) {
            $this->invalid('document', __('The selected document does not belong to this contract version.'));
        }

        return $locked;
    }

    private function createDocumentVersion(ContractVersion $contractVersion, Document $document, User $actor, array $data): DocumentVersion
    {
        return DocumentVersion::withoutGlobalScopes()->create([
            'company_id' => $contractVersion->company_id,
            'document_id' => $document->id,
            'version_number' => 1,
            'file_name' => $data['file_name'] ?? basename($data['file_path']),
            'file_path' => $data['file_path'],
            'mime_type' => $data['mime_type'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'uploaded_by' => $actor->id,
        ]);
    }

    /** @return array<int, string> */
    private function removeDocumentVersions(Document $document): array
    {
        $paths = [];

        foreach ($document->versions as $version) {
            if ($version->file_path) {
                $paths[] = $version->file_path;
            }
            $version->delete();
        }

        return array_values(array_unique($paths));
    }

    /** @param array<int, null|string> $paths */
    private function deleteUnreferencedFiles(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            $stillReferenced = DocumentVersion::withoutGlobalScopes()
                ->where('file_path', $path)
                ->exists();

            if (! $stillReferenced) {
                Storage::disk(config('filesystems.default'))->delete($path);
            }
        }
    }

    private function assertAllPartiesSigned(ContractVersion $version): void
    {
        $partyIds = $version->parties()->pluck('id');
        $signatureCount = $version->signatures()->whereIn('contract_party_id', $partyIds)->count();
        if ($partyIds->isEmpty() || $signatureCount !== $partyIds->count()) {
            $this->invalid('signatures', __('Record a signature for every contract party before activation.'));
        }
    }

    private function assertAvailable(Contract $contract, ContractVersion $version): void
    {
        $unit = $contract->unit_id
            ? Unit::withoutGlobalScopes()->lockForUpdate()->findOrFail($contract->unit_id)
            : null;
        $planned = $contract->planned_unit_id
            ? ProjectPlannedUnit::withoutGlobalScopes()->lockForUpdate()->findOrFail($contract->planned_unit_id)
            : null;
        $conflicts = Contract::withoutGlobalScopes()
            ->where('company_id', $contract->company_id)
            ->where('status', ContractStatus::Active->value)
            ->whereKeyNot($contract->id);

        if ($contract->contract_type->isSale()) {
            $plannedUnitId = $planned?->id ?? $unit?->planned_unit_id;
            $unitId = $unit?->id;
            $conflicts->where(function ($query) use ($unitId, $plannedUnitId): void {
                if ($unitId) {
                    $query->where('unit_id', $unitId);
                }
                if ($plannedUnitId) {
                    $query->orWhere('planned_unit_id', $plannedUnitId);
                }
            });
            if ($conflicts->exists()) {
                $this->invalid('unit_id', __('This sale target already has an active contract.'));
            }
            return;
        }

        if (! $unit || ! $version->start_date || ! $version->end_date) {
            $this->invalid('dates', __('A lease requires an actual unit, start date, and end date.'));
        }
        $this->assertVersionDates($version);
        $overlap = $conflicts
            ->where('contract_type', ContractType::Lease->value)
            ->where('unit_id', $unit->id)
            ->whereHas('versions', fn ($query) => $query
                ->where('status', ContractVersionStatus::Signed->value)
                ->whereDate('start_date', '<=', $version->end_date)
                ->whereDate('end_date', '>=', $version->start_date))
            ->exists();
        if ($overlap) {
            $this->invalid('unit_id', __('This lease period overlaps an active lease contract.'));
        }
    }

    private function assertVersionDates(ContractVersion $version): void
    {
        if ($version->contract->contract_type === ContractType::Lease
            && (! $version->start_date || ! $version->end_date || $version->end_date->lt($version->start_date))) {
            $this->invalid('end_date', __('A lease needs an end date after its start date.'));
        }
    }

    /**
     * An active agreement stays active while an amendment version is being
     * reviewed. The version has its own workflow state; it must not make the
     * already-signed agreement appear to be merely under review or a draft.
     */
    private function contractWorkflowStatus(Contract $contract, ContractVersion $version, ContractStatus $normalStatus): ContractStatus
    {
        if ($contract->status === ContractStatus::Active
            && ContractAmendment::withoutGlobalScopes()
                ->where('contract_id', $contract->id)
                ->where('resulting_contract_version_id', $version->id)
                ->exists()) {
            return ContractStatus::Active;
        }

        return $normalStatus;
    }

    private function lockedVersion(ContractVersion $version, array $with = []): ContractVersion
    {
        return ContractVersion::withoutGlobalScopes()
            ->with(array_merge(['contract'], $with))
            ->lockForUpdate()
            ->findOrFail($version->id);
    }

    private function history(Contract $contract, ContractVersion $version, User $actor, ?string $from, string $to, string $action, ?string $reason = null): void
    {
        ContractStatusHistory::withoutGlobalScopes()->create([
            'company_id' => $contract->company_id,
            'contract_id' => $contract->id,
            'contract_version_id' => $version->id,
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'reason' => $reason,
            'actor_id' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    private function can(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException;
        }
    }

    private function sameCompany(User $user, int $companyId): void
    {
        if (! $user->isSuperAdmin() && (int) $user->company_id !== $companyId) {
            throw new AuthorizationException;
        }
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
