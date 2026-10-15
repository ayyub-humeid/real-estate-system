<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OfferApprovalStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): ?string
    {
        return __('crm.offers.approval_statuses.' . $this->value);
    }
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger'
        };
    }
}
