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
    /** Set by an activated sale contract, or by hand for a sale made outside the system (guarded). */
    case Sold = 'sold';
    /** Set by an activated lease contract (released when it is ended), or by hand for a rental made outside the system (guarded). */
    case Rented = 'rented';

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])->all();
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
            self::Sold => $to === self::Ready, // manual correction only; the service refuses it while a contract holds the unit
        };
    }
}
