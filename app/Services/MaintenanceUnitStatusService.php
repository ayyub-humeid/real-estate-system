<?php

namespace App\Services;

use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Models\UnitStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a unit's physical status in step with its maintenance requests, so nobody
 * has to remember to do it by hand.
 *
 * Only a Ready unit is taken out of service. Rented, Sold and Reserved units belong
 * to a contract or a reservation, and changing them would break that workflow.
 * Only a unit this service put into Maintenance is ever returned to Ready, so a
 * status chosen by a person is never overwritten.
 */
class MaintenanceUnitStatusService
{
    public const HOLD_REASON = 'maintenance_request_opened';

    public const RELEASE_REASON = 'maintenance_requests_closed';

    public function __construct(private readonly UnitStatusService $unitStatuses) {}

    public function hold(int $unitId): void
    {
        DB::transaction(function () use ($unitId): void {
            $unit = Unit::withoutGlobalScopes()->lockForUpdate()->find($unitId);

            if ($unit?->status !== UnitStatus::Ready->value) {
                return;
            }

            $this->unitStatuses->change($unit, UnitStatus::Maintenance, self::HOLD_REASON, Auth::id());
        });
    }

    public function release(int $unitId): void
    {
        DB::transaction(function () use ($unitId): void {
            $unit = Unit::withoutGlobalScopes()->lockForUpdate()->find($unitId);

            if ($unit?->status !== UnitStatus::Maintenance->value
                || $this->hasOpenRequests($unitId)
                || ! $this->isHeldByMaintenance($unitId)) {
                return;
            }

            $this->unitStatuses->change($unit, UnitStatus::Ready, self::RELEASE_REASON, Auth::id());
        });
    }

    public function hasOpenRequests(int $unitId): bool
    {
        return MaintenanceRequest::withoutGlobalScopes()
            ->where('unit_id', $unitId)
            ->whereIn('status', MaintenanceStatus::openValues())
            ->exists();
    }

    /** True when the unit's latest status change was made by a maintenance request. */
    public function isHeldByMaintenance(int $unitId): bool
    {
        return UnitStatusHistory::withoutGlobalScopes()
            ->where('unit_id', $unitId)
            ->latest('id')
            ->value('reason') === self::HOLD_REASON;
    }
}
