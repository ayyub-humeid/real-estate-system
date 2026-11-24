<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanLimitsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    private function withPanelRole(User $user): User
    {
        $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('plan_test_role', 'web'));

        return $user;
    }

    private function companyOnPlan(array $limits, string $status = 'active', ?\DateTimeInterface $endsAt = null): array
    {
        $company = Company::create(['name' => 'Limit Co '.uniqid(), 'email' => uniqid().'@example.test']);
        $plan = Plan::create([
            'name' => 'Tiny '.uniqid(),
            'slug' => 'tiny-'.uniqid(),
            'price' => 1,
            'billing_cycle' => 'monthly',
            'features' => $limits,
            'is_active' => true,
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'starts_at' => now()->subDay(),
            'ends_at' => $endsAt ?? now()->addMonth(),
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        return [$company, $user];
    }

    private function newProperty(Company $company, string $name): Property
    {
        $location = Location::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'City'],
            ['type' => 'city'],
        );

        return Property::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'location_id' => $location->id,
            'name' => $name,
            'address' => 'Addr',
        ]);
    }

    public function test_property_limit_blocks_the_next_property_with_a_clear_message(): void
    {
        [$company, $user] = $this->companyOnPlan(['max_properties' => 1]);
        $this->actingAs($user);

        $this->newProperty($company, 'First');

        try {
            $this->newProperty($company, 'Second');
            $this->fail('The second property should have been refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('maximum number of properties', $e->errors()['limit'][0]);
        }

        $this->assertSame(1, Property::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_unit_limit_blocks_the_next_unit_with_a_clear_message(): void
    {
        [$company, $user] = $this->companyOnPlan(['max_units' => 1]);
        $this->actingAs($user);
        $property = $this->newProperty($company, 'Tower');

        Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'U-1', 'status' => 'draft']);

        try {
            Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'U-2', 'status' => 'draft']);
            $this->fail('The second unit should have been refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('maximum number of units', $e->errors()['limit'][0]);
        }
    }

    private function newProject(Company $company, string $name, string $status = 'planning'): \App\Models\Project
    {
        $location = Location::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'City'],
            ['type' => 'city'],
        );

        return \App\Models\Project::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'location_id' => $location->id,
            'name' => $name,
            'project_type' => 'residential',
            'status' => $status,
        ]);
    }

    public function test_project_limit_blocks_the_next_active_project(): void
    {
        [$company, $user] = $this->companyOnPlan(['max_projects' => 2]);
        $this->actingAs($user);

        $this->newProject($company, 'Alpha');
        $this->newProject($company, 'Beta');

        try {
            $this->newProject($company, 'Gamma');
            $this->fail('A third active project should have been refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('maximum number of active projects', $e->errors()['limit'][0]);
        }
    }

    public function test_a_completed_project_frees_its_slot(): void
    {
        [$company, $user] = $this->companyOnPlan(['max_projects' => 2]);
        $this->actingAs($user);

        $this->newProject($company, 'Alpha')->update(['status' => 'completed']);
        $this->newProject($company, 'Beta');

        $this->assertTrue($company->fresh()->canAddProject());
        $this->assertInstanceOf(\App\Models\Project::class, $this->newProject($company, 'Gamma'));
    }

    public function test_user_limit_blocks_the_next_user_with_a_clear_message(): void
    {
        [$company, $user] = $this->companyOnPlan(['max_users' => 1]);
        $this->actingAs($user);

        try {
            User::factory()->create(['company_id' => $company->id]);
            $this->fail('A second user should have been refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('maximum number of users', $e->errors()['limit'][0]);
        }
    }

    public function test_an_expired_subscription_shows_the_upgrade_message_instead_of_the_panel(): void
    {
        [, $user] = $this->companyOnPlan(['max_units' => 5], 'active', now()->subDay());

        $this->actingAs($this->withPanelRole($user))
            ->withSession(['locale' => 'en'])
            ->get('/admin')
            ->assertSee('Your subscription has expired or is inactive');
    }

    public function test_a_company_without_any_subscription_is_blocked_from_the_panel(): void
    {
        $company = Company::create(['name' => 'No Sub Co', 'email' => 'nosub@example.test']);
        $user = $this->withPanelRole(User::factory()->create(['company_id' => $company->id]));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get('/admin')
            ->assertSee('Your subscription has expired or is inactive');
    }
}
