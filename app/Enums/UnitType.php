<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Shared controlled business catalogue for planned and physical Units. */
enum UnitType: string implements HasLabel
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
            self::Apartment->value => self::Apartment->getLabel(), self::Studio->value => self::Studio->getLabel(),
            self::Duplex->value => self::Duplex->getLabel(), self::Penthouse->value => self::Penthouse->getLabel(),
            self::Villa->value => self::Villa->getLabel(), self::Townhouse->value => self::Townhouse->getLabel(),
            self::Office->value => self::Office->getLabel(), self::Retail->value => self::Retail->getLabel(),
            self::Warehouse->value => self::Warehouse->getLabel(), self::Parking->value => self::Parking->getLabel(),
            self::Storage->value => self::Storage->getLabel(), self::Other->value => self::Other->getLabel(),
        ];
    }

    public function getLabel(): ?string
    {
        return __(match ($this) {
            self::Apartment => 'Apartment', self::Studio => 'Studio', self::Duplex => 'Duplex',
            self::Penthouse => 'Penthouse', self::Villa => 'Villa', self::Townhouse => 'Townhouse',
            self::Office => 'Office', self::Retail => 'Retail', self::Warehouse => 'Warehouse',
            self::Parking => 'Parking Space', self::Storage => 'Storage', self::Other => 'Other',
        });
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && self::tryFrom($value) !== null;
    }
}
