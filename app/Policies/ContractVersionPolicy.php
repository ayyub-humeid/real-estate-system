<?php

namespace App\Policies;

use App\Enums\ContractVersionStatus;
use App\Models\{ContractVersion, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class ContractVersionPolicy
{
    use CompanyOwnedPolicy;

    public function view(User $user, ContractVersion $record): bool
    {
        return $this->canForRecord($user, 'view_contract', $record);
    }

    public function updateDraft(User $user, ContractVersion $record): bool
    {
        return in_array($record->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true)
            && $this->canForRecord($user, 'update_contract_version', $record);
    }

    public function submit(User $user, ContractVersion $record): bool
    {
        return in_array($record->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true)
            && $this->canForRecord($user, 'submit_contract_version', $record);
    }

    public function approve(User $user, ContractVersion $record): bool
    {
        return $record->status === ContractVersionStatus::Submitted && $this->canForRecord($user, 'approve_contract_version', $record);
    }

    public function requestRevision(User $user, ContractVersion $record): bool
    {
        return $record->status === ContractVersionStatus::Submitted && $this->canForRecord($user, 'request_contract_revision', $record);
    }

    public function createRevision(User $user, ContractVersion $record): bool
    {
        return $record->status === ContractVersionStatus::Approved
            && $this->canForRecord($user, 'create_contract_revision', $record);
    }

    public function attachDocument(User $user, ContractVersion $record): bool
    {
        return in_array($record->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true)
            && $this->canForRecord($user, 'attach_contract_document', $record);
    }

    public function recordSignature(User $user, ContractVersion $record): bool
    {
        return $record->status === ContractVersionStatus::Approved && $this->canForRecord($user, 'record_contract_signature', $record);
    }
}
