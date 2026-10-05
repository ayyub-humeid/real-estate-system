<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaseTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure role exists to prevent PaymentObserver crashing
        \App\Models\Role::platform()->firstOrCreate(['name' => 'financial_manager', 'guard_name' => 'web']);

        $company = \App\Models\Company::firstOrCreate(
            ['email' => 'demo' . uniqid() . '@example.com'],
            [
                'name' => 'Demo Company ' . uniqid(),
                'phone' => '1234567890',
                'is_active' => true,
            ]
        );

        $user = \App\Models\User::firstOrCreate(
            ['email' => 'tenant@example.com'],
            ['name' => 'Test Tenant', 'password' => bcrypt('password'), 'company_id' => $company->id]
        );

        $tenant = \App\Models\Tenant::firstOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $company->id,
                'status' => 'active',
            ]
        );

        $location = \App\Models\Location::firstOrCreate(
            ['name' => 'Downtown'],
            ['type' => 'city']
        );

        $property = \App\Models\Property::firstOrCreate(
            ['name' => 'Sunset Apartments'],
            [
                'company_id' => $company->id,
                'location_id' => $location->id,
                'address' => '123 Sunshine Blvd',
            ]
        );

        $unit = \App\Models\Unit::firstOrCreate(
            ['unit_number' => '101A', 'property_id' => $property->id],
            [
                'company_id' => $company->id,
                'type' => 'apartment',
                'status' => 'available',
                'rent_price' => 1000,
            ]
        );

        $lease = \App\Models\Lease::create([
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'rent_amount' => 12000, // Total contract value
            'payment_frequency' => 'monthly',
            'payment_day' => 1,
            'status' => 'active',
        ]);

        // Create payments
        \App\Models\Payment::create([
            'company_id' => $company->id,
            'lease_id' => $lease->id,
            'amount' => 1000,
            'paid_amount' => 1000, 
            'due_date' => now(),
            'status' => 'paid',
            'recorded_by' => $user->id,
        ]);

        \App\Models\Payment::create([
            'company_id' => $company->id,
            'lease_id' => $lease->id,
            'amount' => 1000,
            'paid_amount' => 500, 
            'due_date' => now()->addMonth(),
            'status' => 'partial',
            'recorded_by' => $user->id,
        ]);
        
        \App\Models\Payment::create([
            'company_id' => $company->id,
            'lease_id' => $lease->id,
            'amount' => 1000,
            'paid_amount' => 0, 
            'due_date' => now()->addMonths(2),
            'status' => 'pending',
            'recorded_by' => $user->id,
        ]);
    }
}
