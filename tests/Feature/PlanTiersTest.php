<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Database\Seeders\PlanAndSubscriptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTiersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_three_tiers_with_yearly_price_and_trial(): void
    {
        $this->seed(PlanAndSubscriptionSeeder::class);

        $active = Plan::where('is_active', true)->orderBy('price')->get();

        $this->assertSame(['starter', 'growth', 'business'], $active->pluck('slug')->all());
        $this->assertSame([99.0, 249.0, 599.0], $active->map(fn ($p) => (float) $p->price)->all());
        $this->assertTrue($active->every(fn ($p) => $p->annual_price !== null && $p->annual_price < $p->price * 12));
        $this->assertTrue($active->every(fn ($p) => $p->trial_days === 30));
        $this->assertSame([2, 10, null], $active->map(fn ($p) => $p->features['max_projects'])->all());
    }

    public function test_every_plan_includes_the_core_modules(): void
    {
        $this->seed(PlanAndSubscriptionSeeder::class);

        foreach (Plan::where('is_active', true)->get() as $plan) {
            foreach (['maintenance_tracking', 'rental_requests', 'expense_tracking', 'document_management'] as $feature) {
                $this->assertTrue($plan->features[$feature], "{$plan->slug} must include {$feature}");
            }
        }
    }

    public function test_business_has_no_property_or_unit_limit_and_starter_does(): void
    {
        $this->seed(PlanAndSubscriptionSeeder::class);

        $business = Company::create(['name' => 'Big Co', 'email' => 'big@example.test']);
        $starter = Company::create(['name' => 'Small Co', 'email' => 'small@example.test']);

        foreach ([[$business, 'business'], [$starter, 'starter']] as [$company, $slug]) {
            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => Plan::where('slug', $slug)->value('id'),
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ]);
        }

        $this->assertNull($business->fresh()->getPlanFeature('max_units'));
        $this->assertSame(50, $starter->fresh()->getPlanFeature('max_units'));
        $this->assertSame(2, $starter->fresh()->getPlanFeature('max_projects'));
    }
}
