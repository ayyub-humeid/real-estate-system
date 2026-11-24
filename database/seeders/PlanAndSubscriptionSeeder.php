<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Company;

class PlanAndSubscriptionSeeder extends Seeder
{
    /** Core modules every company gets on every plan — not gated. */
    private const CORE = [
        'maintenance_tracking' => true,
        'rental_requests'      => true,
        'expense_tracking'     => true,
    ];

    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For a small developer or agency running up to two live projects.',
                'price' => 99.00,
                'annual_price' => 990.00,
                'trial_days' => 30,
                'billing_cycle' => 'monthly',
                'features' => self::CORE + [
                    'max_projects'         => 2,
                    'max_properties'       => 5,
                    'max_units'            => 50,
                    'max_employees'        => 5,
                    'max_users'            => 5,
                    'construction_tracking' => false,
                    'due_diligence'        => false,
                    'advanced_reports'     => false,
                    'export_data'          => false,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'For a developer running several projects with a full sales and finance team.',
                'price' => 249.00,
                'annual_price' => 2490.00,
                'trial_days' => 30,
                'billing_cycle' => 'monthly',
                'features' => self::CORE + [
                    'max_projects'         => 10,
                    'max_properties'       => 25,
                    'max_units'            => 300,
                    'max_employees'        => 20,
                    'max_users'            => 20,
                    'construction_tracking' => true,
                    'due_diligence'        => false,
                    'advanced_reports'     => false,
                    'export_data'          => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For larger firms that need no limit on projects, properties or units.',
                'price' => 599.00,
                'annual_price' => 5990.00,
                'trial_days' => 30,
                'billing_cycle' => 'monthly',
                'features' => self::CORE + [
                    'max_projects'         => null,
                    'max_properties'       => null,
                    'max_units'            => null,
                    'max_employees'        => 100,
                    'max_users'            => 100,
                    'construction_tracking' => true,
                    'due_diligence'        => true,
                    'advanced_reports'     => true,
                    'export_data'          => true,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(['slug' => $planData['slug']], $planData);
        }

        // Drop the old placeholder plans; keep (inactive) only one a company is still subscribed to.
        $old = Plan::whereIn('slug', ['basic', 'professional', 'enterprise']);
        (clone $old)->doesntHave('subscriptions')->get()->each->delete();
        $old->update(['is_active' => false]);

        $growth = Plan::where('slug', 'growth')->first();
        $starter = Plan::where('slug', 'starter')->first();

        foreach (Company::all() as $company) {
            if ($company->subscription()->exists()) {
                continue;
            }

            if ($company->name === 'Emaar Properties') {
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $growth->id,
                    'status' => 'active',
                    'starts_at' => now()->subMonths(1),
                    'ends_at' => now()->addMonths(11),
                ]);
            } elseif ($company->name === 'Damac Properties') {
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $starter->id,
                    'status' => 'active',
                    'starts_at' => now()->subDays(5),
                    'ends_at' => now()->addDays(25),
                ]);
            }
        }
    }
}
