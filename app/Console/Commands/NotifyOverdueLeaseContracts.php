<?php

namespace App\Console\Commands;

use App\Services\LeaseExpiryAlertService;
use Illuminate\Console\Command;

class NotifyOverdueLeaseContracts extends Command
{
    protected $signature = 'contracts:notify-overdue-leases';
    protected $description = 'Notify managers about active lease contracts whose end date has passed. Contracts are never ended automatically.';

    public function handle(LeaseExpiryAlertService $service): int
    {
        $this->info(__('contract_domain.end.console', ['count' => $service->notifyOverdue()]));
        return self::SUCCESS;
    }
}
