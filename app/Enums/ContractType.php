<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasLabel
{
    case Sale = 'sale';
    case OffPlanSale = 'off_plan_sale';
    case Lease = 'lease';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Sale => __('Sale'),
            self::OffPlanSale => __('Off-plan sale'),
            self::Lease => __('Lease'),
        };
    }

    public function requiresActualUnit(): bool
    {
        return $this !== self::OffPlanSale;
    }

    public function requiresPlannedUnit(): bool
    {
        return $this === self::OffPlanSale;
    }

    public function isSale(): bool
    {
        return $this === self::Sale || $this === self::OffPlanSale;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])
            ->all();
    }
}
