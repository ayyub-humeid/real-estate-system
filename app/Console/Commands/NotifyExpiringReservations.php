<?php

namespace App\Console\Commands;

use App\Services\SalesNotifier;
use Illuminate\Console\Command;

class NotifyExpiringReservations extends Command
{
    protected $signature = 'reservations:notify-expiring {--days=2 : Warn this many days before a reservation ends}';

    protected $description = 'Warn the people behind active reservations that run out soon, once per end date';

    public function handle(SalesNotifier $notifier): int
    {
        $count = $notifier->notifyExpiringSoon(max(1, (int) $this->option('days')));
        $this->info("Warned about {$count} reservation(s).");

        return self::SUCCESS;
    }
}
