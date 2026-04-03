<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckLeaseExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-lease-expirations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting automated lease and payment checks...');

        // 1. Check Expiring Leases (in 30 days)
        $expiringLeases = \App\Models\Lease::expiringSoon(30)->get();
        $this->info("Found {$expiringLeases->count()} leases expiring soon.");

        foreach ($expiringLeases as $lease) {
            /** @var \App\Models\Lease $lease */
            // Notify Property Managers
            $managers = \App\Models\User::where('company_id', $lease->company_id)
                ->role('property_manager')
                ->get();

            foreach ($managers as $manager) {
                $manager->notify(new \App\Notifications\LeaseExpiringNotification($lease));
            }

            // Notify Tenant
            if ($lease->tenant && $lease->tenant->user) {
                $lease->tenant->user->notify(new \App\Notifications\LeaseExpiringNotification($lease));
            }
        }

        // 2. Check Overdue Payments
        $overduePayments = \App\Models\Payment::overdue()->get();
        $this->info("Found {$overduePayments->count()} overdue payments.");

        foreach ($overduePayments as $payment) {
            // Notify Financial Managers
            $managers = \App\Models\User::where('company_id', $payment->company_id)
                ->role('financial_manager')
                ->get();

            foreach ($managers as $manager) {
                $manager->notify(new \App\Notifications\PaymentOverdueNotification($payment));
            }

            // Notify Tenant
            $tenantUser = $payment->lease->tenant->user ?? null;
            if ($tenantUser) {
                $tenantUser->notify(new \App\Notifications\PaymentOverdueNotification($payment));
            }
        }

        $this->info('Automated checks completed successfully.');
    }
}
