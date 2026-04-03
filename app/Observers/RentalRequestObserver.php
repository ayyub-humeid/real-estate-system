<?php

namespace App\Observers;

use App\Models\RentalRequest;

class RentalRequestObserver
{
    /**
     * Handle the RentalRequest "created" event.
     */
    public function created(RentalRequest $rentalRequest): void
    {
        // Notify Property Managers in the same company
        $managers = \App\Models\User::where('company_id', $rentalRequest->company_id)
            ->role('property_manager')
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new \App\Notifications\RentalRequestNotification($rentalRequest));
        }
    }

    /**
     * Handle the RentalRequest "updated" event.
     */
    public function updated(RentalRequest $rentalRequest): void
    {
        //
    }

    /**
     * Handle the RentalRequest "deleted" event.
     */
    public function deleted(RentalRequest $rentalRequest): void
    {
        //
    }

    /**
     * Handle the RentalRequest "restored" event.
     */
    public function restored(RentalRequest $rentalRequest): void
    {
        //
    }

    /**
     * Handle the RentalRequest "force deleted" event.
     */
    public function forceDeleted(RentalRequest $rentalRequest): void
    {
        //
    }
}
