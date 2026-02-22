<?php
// database/seeders/LeaseSeeder.php

namespace Database\Seeders;

use App\Models\Lease;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeaseSeeder extends Seeder
{
    public function run(): void
    {
        // Get a company
        $company = \App\Models\Company::first();
        
        // Get a tenant user
        $tenant = User::where('company_id', $company->id)
            ->where('role', 'tenant')
            ->first();
        
        // Get an available unit
        $unit = Unit::where('status', 'available')->first();
        
        if ($company && $tenant && $unit) {
            $lease = Lease::create([
                'company_id' => $company->id,
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'start_date' => now(),
                'end_date' => now()->addYear(),
                'rent_amount' => 1200.00,
                'deposit_amount' => 1200.00,
                'payment_frequency' => 'monthly',
                'payment_day' => 1,
                'status' => 'active',
            ]);

            // Update unit status
            $unit->update(['status' => 'rented']);

            // Generate payment schedule
            $lease->generatePaymentSchedule();
        }
    }
}