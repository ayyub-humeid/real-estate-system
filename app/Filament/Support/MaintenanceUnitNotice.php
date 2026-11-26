<?php

namespace App\Filament\Support;

use App\Enums\UnitStatus;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Services\MaintenanceUnitStatusService;
use Filament\Notifications\Notification;

/** Tells the admin what a saved maintenance request did (or did not do) to its unit. */
class MaintenanceUnitNotice
{
    public static function send(MaintenanceRequest $request): void
    {
        if (! $request->wasRecentlyCreated && ! $request->wasChanged(['status', 'unit_id'])) {
            return;
        }

        $unit = Unit::withoutGlobalScopes()->find($request->unit_id);

        if (! $unit) {
            return;
        }

        $service = app(MaintenanceUnitStatusService::class);
        $status = UnitStatus::tryFrom((string) $unit->status);
        $inMaintenance = $status === UnitStatus::Maintenance;

        $key = match (true) {
            $request->isOpen() && $inMaintenance => $service->isHeldByMaintenance($unit->id) ? 'held' : 'already_in_maintenance',
            $request->isOpen() => 'not_moved',
            $inMaintenance => $service->hasOpenRequests($unit->id) ? 'other_requests_open' : 'set_by_person',
            $status === UnitStatus::Ready && ! $request->wasRecentlyCreated => 'released',
            default => null,
        };

        if ($key === null) {
            return;
        }

        $params = ['unit' => $unit->unit_number, 'status' => $status?->getLabel() ?? $unit->status];

        Notification::make()
            ->status(in_array($key, ['held', 'released'], true) ? 'success' : 'warning')
            ->title(__("maintenance.unit_notice.{$key}.title", $params))
            ->body(__("maintenance.unit_notice.{$key}.body", $params))
            ->persistent()
            ->send();
    }
}
