<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationSource: string implements HasLabel
{
    case AcceptedOffer = 'accepted_offer'; case Direct = 'direct';
    public function getLabel(): ?string { return __('reservations.sources.'.$this->value); }
    public static function options(): array { return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all(); }
}
