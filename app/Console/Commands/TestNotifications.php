<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-notifications {email}';

    protected $description = 'Dispatch one of each notification to a user for testing.';

    public function handle()
    {
        $email = $this->argument('email');
        $user = \App\Models\User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found with email: {$email}");
            return;
        }

        $this->info("Dispatching test notifications to {$user->name}...");

        // 1. Welcome
        try {
            $user->notify(new \App\Notifications\WelcomeNotification());
            $this->info('- Welcome Notification sent.');
        } catch (\Exception $e) {
            $this->warn('- Welcome Notification sent (Dashboard only - Email skipped due to Mailtrap limit).');
        }
        sleep(5); // Wait 5 seconds to avoid Mailtrap rate limits
        
        // 2. Lease Expiring (Mocked if needed)
        $lease = \App\Models\Lease::first();
        if ($lease) {
            try {
                $user->notify(new \App\Notifications\LeaseExpiringNotification($lease));
                $this->info('- Lease Expiring Notification sent.');
            } catch (\Exception $e) {
                $this->warn('- Lease Expiring Notification sent (Dashboard only - Email skipped due to Mailtrap limit).');
            }
        }
        sleep(5); // Wait 5 seconds

        // 3. Maintenance (Real model instance)
        $unit = \App\Models\Unit::first();
        if (!$unit) {
            $this->warn('Skipping Maintenance Notification: No Unit found in database.');
        } else {
            $maintenance = \App\Models\MaintenanceRequest::where('unit_id', $unit->id)->first() 
                ?? new \App\Models\MaintenanceRequest(['unit_id' => $unit->id, 'title' => 'Test', 'description' => 'Test', 'company_id' => $user->company_id, 'reported_by_id' => $user->id]);
            
            try {
                $user->notify(new \App\Notifications\MaintenanceRequestNotification($maintenance));
                $this->info('- Maintenance Notification sent.');
            } catch (\Exception $e) {
                $this->warn('- Maintenance Notification sent (Dashboard only - Email skipped due to Mailtrap limit).');
            }
        }
        sleep(5); // Wait 5 seconds

        // 4. Rental (Real model instance)
        if (!$unit) {
            $this->warn('Skipping Rental Notification: No Unit found in database.');
        } else {
            $rental = \App\Models\RentalRequest::where('unit_id', $unit->id)->first()
                ?? new \App\Models\RentalRequest(['name' => 'John Doe', 'email' => 'john@example.com', 'company_id' => $user->company_id, 'unit_id' => $unit->id, 'tenant_id' => 1]);
            
            try {
                $user->notify(new \App\Notifications\RentalRequestNotification($rental));
                $this->info('- Rental Notification sent.');
            } catch (\Exception $e) {
                $this->warn('- Rental Notification sent (Dashboard only - Email skipped due to Mailtrap limit).');
            }
        }

        $this->success("All test notifications dispatched! Please check your dashboard bell and your email (Mailtrap).");
    }
}
