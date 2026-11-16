<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Currency: string implements HasLabel
{
    case USD = 'USD';
    case ILS = 'ILS';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::USD => 'US Dollar ($)',
            self::ILS => 'Israeli Shekel (₪)',
        };
    }

    public function getSymbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::ILS => '₪',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->all();
    }

    public static function symbols(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getSymbol()])
            ->all();
    }
}