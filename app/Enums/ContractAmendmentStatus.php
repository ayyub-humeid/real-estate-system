<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractAmendmentStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Effective = 'effective';

    public function getLabel(): ?string
    {
        return __('contract_domain.amendment_statuses.' . $this->value);
    }

    public function color(): string
    {
        return $this === self::Effective ? 'success' : 'gray';
    }
}
