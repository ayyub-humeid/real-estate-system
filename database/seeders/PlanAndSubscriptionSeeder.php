<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Company;
use Carbon\Carbon;

class PlanAndSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Plans
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Perfect for small property owners.',
                'price' => 29.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_properties' => 2,
                    'max_units' => 10,
                    'max_employees' => 2,
                    'max_users' => 5,
                    'maintenance_tracking' => true,
                    'accounting' => false,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing real estate agencies.',
                'price' => 99.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_properties' => 10,
                    'max_units' => 50,
                    'max_employees' => 10,
                    'max_users' => 20,
                    'maintenance_tracking' => true,
                    'accounting' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited power for large corporations.',
                'price' => 299.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_properties' => 999,
                    'max_units' => 9999,
                    'max_employees' => 100,
                    'max_users' => 250,
                    'maintenance_tracking' => true,
                    'accounting' => true,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(['slug' => $planData['slug']], $planData);
        }

        $basicPlan = Plan::where('slug', 'basic')->first();
        $profPlan = Plan::where('slug', 'professional')->first();

        // 2. Assign Subscriptions to Companies
        $companies = Company::all();

        foreach ($companies as $company) {
            if ($company->name === 'Al-Nour Real Estate') {
                // Active Premium Subscription
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $profPlan->id,
                    'status' => 'active',
                    'starts_at' => now()->subMonths(1),
                    'ends_at' => now()->addMonths(11),
                ]);
            } elseif ($company->name === 'Horizon Properties') {
                // Expired Subscription (to test blocking)
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $basicPlan->id,
                    'status' => 'active',
                    'starts_at' => now()->subMonths(2),
                    'ends_at' => now()->subDays(1), // Already expired
                ]);
            } elseif ($company->name === 'GulfNest Realty') {
                // Active Basic
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $basicPlan->id,
                    'status' => 'active',
                    'starts_at' => now()->subDays(5),
                    'ends_at' => now()->addDays(25),
                ]);
            }
        }
    }
}
