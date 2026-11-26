<?php

namespace App\Observers;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Notifications\MaintenanceRequestNotification;
use App\Notifications\MaintenanceStatusUpdatedNotification;
use App\Services\MaintenanceUnitStatusService;

/** Runs for every source of a request: admin panel, unit screen, tenant API and seeders. */
class MaintenanceRequestObserver
{
    public function __construct(private readonly MaintenanceUnitStatusService $unitStatuses) {}

    public function created(MaintenanceRequest $maintenanceRequest): void
    {
        if ($maintenanceRequest->isOpen()) {
            $this->unitStatuses->hold((int) $maintenanceRequest->unit_id);
        }

        // Notify whoever may handle requests in this company, whatever their role is called.
        User::where('company_id', $maintenanceRequest->company_id)
            ->get()
            ->filter(fn (User $user): bool => $user->can('update_maintenance::request'))
            ->each(fn (User $user) => $user->notify(new MaintenanceRequestNotification($maintenanceRequest)));
    }

    public function updated(MaintenanceRequest $maintenanceRequest): void
    {
        $this->syncUnitStatus($maintenanceRequest);

        if ($maintenanceRequest->wasChanged('status') && $maintenanceRequest->reporter) {
            $maintenanceRequest->reporter->notify(new MaintenanceStatusUpdatedNotification($maintenanceRequest));
        }
    }

    public function deleted(MaintenanceRequest $maintenanceRequest): void
    {
        $this->unitStatuses->release((int) $maintenanceRequest->unit_id);
    }

    private function syncUnitStatus(MaintenanceRequest $request): void
    {
        $movedUnit = $request->wasChanged('unit_id');

        if (! $movedUnit && ! $request->wasChanged('status')) {
            return;
        }

        if ($movedUnit) {
            $this->unitStatuses->release((int) $request->getOriginal('unit_id'));
        }

        if (! $request->isOpen()) {
            $this->unitStatuses->release((int) $request->unit_id);

            return;
        }

        // Moving between two open statuses must not undo a status a person chose,
        // except when the work actually starts.
        $reopened = ! (MaintenanceStatus::tryFrom((string) $request->getOriginal('status'))?->isOpen() ?? false);
        $started = $request->status === MaintenanceStatus::InProgress->value;

        if ($movedUnit || $reopened || $started) {
            $this->unitStatuses->hold((int) $request->unit_id);
        }
    }
}
