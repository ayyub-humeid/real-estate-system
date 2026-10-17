<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\{Unit, UnitReservation};
use Illuminate\Validation\ValidationException;

class ReservationAvailabilityService
{
    /** Must be called after the Unit row is locked by the workflow transaction. */
    public function assertCanActivate(UnitReservation $reservation, Unit $unit): void
    {
        $conflict = UnitReservation::withoutGlobalScopes()
            ->where('company_id', $reservation->company_id)
            ->where('unit_id', $unit->id)
            ->where('status', ReservationStatus::Active->value)
            ->whereKeyNot($reservation->id)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['unit_id' => __('reservations.errors.unit_already_reserved')]);
        }
    }
}
