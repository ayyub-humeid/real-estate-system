<?php

namespace App\Observers;

use App\Models\MaintenanceRequest;

class MaintenanceRequestObserver
{
    /**
     * Handle the MaintenanceRequest "created" event.
     */
    public function created(MaintenanceRequest $maintenanceRequest): void
    {
        // Notify Property Managers in the same company
        $managers = \App\Models\User::where('company_id', $maintenanceRequest->company_id)
            ->role('property_manager')
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new \App\Notifications\MaintenanceRequestNotification($maintenanceRequest));
        }
    }

    /**
     * Handle the MaintenanceRequest "updated" event.
     */
    public function updated(MaintenanceRequest $maintenanceRequest): void
    {
        //
    }

    /**
     * Handle the MaintenanceRequest "deleted" event.
     */
    public function deleted(MaintenanceRequest $maintenanceRequest): void
    {
        //
    }

    /**
     * Handle the MaintenanceRequest "restored" event.
     */
    public function restored(MaintenanceRequest $maintenanceRequest): void
    {
        //
    }

    /**
     * Handle the MaintenanceRequest "force deleted" event.
     */
    public function forceDeleted(MaintenanceRequest $maintenanceRequest): void
    {
        //
    }
}
