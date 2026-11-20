<?php

namespace App\Services;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

/**
 * The single place where a Unit's physical status changes because of a
 * business workflow (reservation, contract). Every change locks the unit row,
 * checks that the transition is allowed and writes one history row, so the
 * Unit Status History tab is always complete.
 *
 * Manual changes made by a person on the unit screen keep using
 * UnitSetupService::transitionStatus (which refuses Sold/Rented).
 *
 * Must be called inside the caller's database transaction.
 */
class UnitStatusService
{
    public function change(Unit|int $unit, UnitStatus $to, string $reason, ?int $changedBy = null): Unit
    {
        $id = $unit instanceof Unit ? $unit->id : $unit;
        $locked = Unit::withoutGlobalScopes()->lockForUpdate()->findOrFail($id);
        $from = UnitStatus::tryFrom((string) $locked->status);

        if ($from === $to) {
            return $locked;
        }

        if (! $from || ! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'unit_id' => __('unit.errors.cannot_change_status', ['from' => $from?->getLabel() ?? $locked->status, 'to' => $to->getLabel()]),
            ]);
        }

        $locked->update(['status' => $to->value]);
        $locked->statusHistories()->create([
            'company_id' => $locked->company_id,
            'unit_id' => $locked->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'changed_by' => $changedBy,
            'changed_at' => now(),
            'created_at' => now(),
        ]);

        return $locked->refresh();
    }
}
