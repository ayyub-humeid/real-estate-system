<?php

namespace App\Enums;

enum ListingType: string
{
    case Sale = 'sale';
    case Rent = 'rent';

    public static function options(): array
    {
        return [self::Sale->value => 'Sale', self::Rent->value => 'Rent'];
    }
}
