<?php

namespace App\Console\Commands;

use App\Services\ReservationWorkflowService;
use Illuminate\Console\Command;

class ExpireUnitReservations extends Command
{
    protected $signature = 'reservations:expire-due';
    protected $description = 'Expire active unit reservations whose validity time has passed.';

    public function handle(ReservationWorkflowService $reservations): int
    {
        $this->info(__('reservations.console.expired', ['count' => $reservations->expireDueReservations()]));
        return self::SUCCESS;
    }
}
