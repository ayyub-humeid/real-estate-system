<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UnitStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public static function options(): array
    {
        return [self::Draft->value => self::Draft->getLabel(), self::Ready->value => self::Ready->getLabel(), self::Maintenance->value => self::Maintenance->getLabel(), self::Inactive->value => self::Inactive->getLabel()];
    }

    public function getLabel(): ?string
    {
        return __(match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Maintenance => 'Maintenance',
            self::Inactive => 'Inactive',
        });
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Ready, self::Maintenance, self::Inactive], true),
            self::Ready, self::Maintenance => in_array($to, [self::Maintenance, self::Ready, self::Inactive], true) && $to !== $this,
            self::Inactive => $to === self::Draft,
        };
    }
}
