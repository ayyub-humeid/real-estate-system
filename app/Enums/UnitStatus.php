<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public static function options(): array
    {
        return [self::Draft->value => 'Draft', self::Ready->value => 'Ready', self::Maintenance->value => 'Maintenance', self::Inactive->value => 'Inactive'];
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
