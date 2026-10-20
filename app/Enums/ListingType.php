<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ListingType: string implements HasLabel
{
    case Sale = 'sale';
    case Rent = 'rent';

    public static function options(): array
    {
        return [self::Sale->value => self::Sale->getLabel(), self::Rent->value => self::Rent->getLabel()];
    }

    public function getLabel(): ?string
    {
        return __(match ($this) {
            self::Sale => 'Sale',
            self::Rent => 'Rent',
        });
    }
}
