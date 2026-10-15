<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OfferStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Negotiating = 'negotiating';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function getLabel(): ?string
    {
        return __('crm.offers.statuses.' . $this->value);
    }
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Negotiating => 'info',
            self::Accepted => 'success',
            self::Rejected,
            self::Withdrawn => 'danger',
            self::Expired => 'warning'
        };
    }
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->all();
    }
}
