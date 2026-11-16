<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UnitStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Reserved = 'reserved';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public static function options(): array
    {
        return [self::Draft->value => self::Draft->getLabel(), self::Ready->value => self::Ready->getLabel(), self::Reserved->value => self::Reserved->getLabel(), self::Maintenance->value => self::Maintenance->getLabel(), self::Inactive->value => self::Inactive->getLabel()];
    }

    public function getLabel(): ?string
    {
        return __(match ($this) {
            self::Draft => 'unit.statuses.draft',
            self::Ready => 'unit.statuses.ready',
            self::Reserved => 'unit.statuses.reserved',
            self::Maintenance => 'unit.statuses.maintenance',
            self::Inactive => 'unit.statuses.inactive',
        });
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Ready, self::Maintenance, self::Inactive], true),
            self::Ready => in_array($to, [self::Reserved, self::Maintenance, self::Inactive], true),
            self::Reserved => in_array($to, [self::Ready, self::Maintenance, self::Inactive], true),
            self::Maintenance => in_array($to, [self::Ready, self::Maintenance, self::Inactive], true) && $to !== $this,
            self::Inactive => $to === self::Draft,
        };
    }
}
