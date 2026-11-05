<?php

namespace App\Console\Commands;

use App\Services\ContractFinancialReminderService;
use Illuminate\Console\Command;

class SendContractInstallmentReminders extends Command
{
    protected $signature = 'contract-finance:send-installment-reminders';
    protected $description = 'Send idempotent upcoming, due, and overdue contract installment reminders.';

    public function handle(ContractFinancialReminderService $service): int
    {
        $this->info('Created '.$service->send().' contract-finance notification event(s).');
        return self::SUCCESS;
    }
}
