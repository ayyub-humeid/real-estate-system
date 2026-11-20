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
    /** Set only by an activated sale contract. Final: a sold unit is not resold in this system. */
    case Sold = 'sold';
    /** Set only by an activated lease contract; released when the contract is ended. */
    case Rented = 'rented';

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])->all();
    }

    /** Statuses a person may pick by hand. Sold/Rented belong to contracts and are never offered in manual status forms. */
    public static function manualOptions(): array
    {
        return collect(self::cases())
            ->reject(fn (self $case): bool => $case->isSetByContract())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])
            ->all();
    }

    public function isSetByContract(): bool
    {
        return $this === self::Sold || $this === self::Rented;
    }

    public function getLabel(): ?string
    {
        return __('unit.statuses.' . $this->value);
    }

    public function description(): string
    {
        return __('unit.status_descriptions.' . $this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ready => 'success',
            self::Reserved => 'warning',
            self::Maintenance => 'danger',
            self::Inactive => 'gray',
            self::Sold => 'info',
            self::Rented => 'primary',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Ready, self::Maintenance, self::Inactive], true),
            self::Ready => in_array($to, [self::Reserved, self::Maintenance, self::Inactive, self::Sold, self::Rented], true),
            self::Reserved => in_array($to, [self::Ready, self::Maintenance, self::Inactive, self::Sold, self::Rented], true),
            self::Maintenance => in_array($to, [self::Ready, self::Maintenance, self::Inactive], true) && $to !== $this,
            self::Inactive => $to === self::Draft,
            self::Rented => $to === self::Ready,
            self::Sold => false,
        };
    }
}
