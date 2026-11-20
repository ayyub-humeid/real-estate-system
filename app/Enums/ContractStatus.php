<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasLabel
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case Active = 'active';
    /** A lease whose term was closed by a person. Sale contracts never end. */
    case Ended = 'ended';

    public function getLabel(): ?string
    {
        return __('contract_domain.statuses.' . $this->value);
    }

    public function description(): string
    {
        return __('contract_domain.status_descriptions.' . $this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::UnderReview => 'warning',
            self::RevisionRequired => 'danger',
            self::Approved => 'info',
            self::Active => 'success',
            self::Ended => 'gray',
        };
    }
}
