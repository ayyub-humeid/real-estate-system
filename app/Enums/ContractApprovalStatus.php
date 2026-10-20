<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractApprovalStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case RevisionRequired = 'revision_required';

    public function getLabel(): ?string
    {
        return __('contract_domain.approval_statuses.' . $this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::RevisionRequired => 'danger',
        };
    }
}
