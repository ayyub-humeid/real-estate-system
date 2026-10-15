<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Matches the existing Lease payment_frequency business values. */
enum RentFrequency: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnually = 'semi_annually';
    case Yearly = 'yearly';

    public function getLabel(): ?string { return __('crm.offers.rent_frequencies.' . $this->value); }
    public static function options(): array { return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all(); }
}
