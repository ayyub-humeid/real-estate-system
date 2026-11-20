<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasLabel
{
    case Draft = 'draft'; case Pending = 'pending'; case Active = 'active'; case Rejected = 'rejected'; case Cancelled = 'cancelled'; case Expired = 'expired'; case Converted = 'converted';
    public function getLabel(): ?string { return __('reservations.statuses.'.$this->value); }
    public function color(): string { return match ($this) { self::Draft => 'gray', self::Pending => 'warning', self::Active => 'success', self::Rejected, self::Cancelled => 'danger', self::Expired => 'warning', self::Converted => 'info' }; }
    public static function options(): array { return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all(); }
}
