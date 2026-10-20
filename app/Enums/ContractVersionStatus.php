<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractVersionStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case Superseded = 'superseded';
    case Signed = 'signed';

    public function getLabel(): ?string
    {
        return __('contract_domain.version_statuses.' . $this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::RevisionRequired => 'danger',
            self::Approved => 'info',
            self::Superseded => 'gray',
            self::Signed => 'success',
        };
    }
}
