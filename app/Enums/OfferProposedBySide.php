<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OfferProposedBySide: string implements HasLabel
{
    case Customer = 'customer';
    case Company = 'company';

    public function getLabel(): ?string
    {
        return __('crm.offers.proposed_sides.' . $this->value);
    }
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->all();
    }
}
