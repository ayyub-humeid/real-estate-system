<?php

namespace App\Enums;

/** Shared controlled business catalogue for planned and physical Units. */
enum UnitType: string
{
    case Apartment = 'apartment';
    case Studio = 'studio';
    case Duplex = 'duplex';
    case Penthouse = 'penthouse';
    case Villa = 'villa';
    case Townhouse = 'townhouse';
    case Office = 'office';
    case Retail = 'retail';
    case Warehouse = 'warehouse';
    case Parking = 'parking';
    case Storage = 'storage';
    case Other = 'other';

    public static function options(): array
    {
        return [
            self::Apartment->value => 'Apartment', self::Studio->value => 'Studio',
            self::Duplex->value => 'Duplex', self::Penthouse->value => 'Penthouse',
            self::Villa->value => 'Villa', self::Townhouse->value => 'Townhouse',
            self::Office->value => 'Office', self::Retail->value => 'Retail',
            self::Warehouse->value => 'Warehouse', self::Parking->value => 'Parking Space',
            self::Storage->value => 'Storage', self::Other->value => 'Other',
        ];
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && self::tryFrom($value) !== null;
    }
}
