<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OfferRevisionStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Superseded = 'superseded';
    case Agreed = 'agreed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function getLabel(): ?string
    {
        return __('crm.offers.revision_statuses.' . $this->value);
    }
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Agreed => 'success',
            self::Rejected,
            self::Withdrawn => 'danger',
            self::Superseded,
            self::Expired => 'warning'
        };
    }
}
