<?php

namespace Tests\Feature;

use App\Filament\Resources\ListingResource\Pages\{CreateListing, ViewListing};
use App\Models\{Company, CompanySetting, Listing, Location, Project, ProjectBuilding, ProjectBuildingFloor, ProjectPlannedUnit, Property, Unit, User};
use App\Services\ListingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ListingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $suffix): Company
    {
        return Company::create(['name' => "Company {$suffix}", 'email' => "{$suffix}@example.test"]);
    }

    private function actor(Company $company, array $permissions = []): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }

    private function listingPermissions(): array
    {
        return ['view_any_listing', 'create_listing', 'update_listing', 'view_listing', 'publish_listing', 'pause_listing', 'archive_listing', 'restore_listing', 'change_listing_price'];
    }

    private function property(Company $company): Property
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => "City {$company->id}", 'type' => 'city']);
        return Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => "Property {$company->id}", 'address' => 'Address']);
    }

    private function actualUnit(Company $company, string $status = 'ready'): Unit
    {
        return Unit::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'property_id' => $this->property($company)->id,
            'unit_number' => 'U-' . uniqid(),
            'type' => 'apartment',
            'status' => $status,
        ]);
    }

    private function plannedUnit(Company $company, string $status = 'approved'): ProjectPlannedUnit
    {
        $project = Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Project', 'project_type' => 'residential', 'currency' => 'USD']);
        $building = ProjectBuilding::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_id' => $project->id, 'name' => 'Tower']);
        $floor = ProjectBuildingFloor::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_building_id' => $building->id, 'floor_number' => 1]);
        return ProjectPlannedUnit::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_building_floor_id' => $floor->id, 'code' => 'P-' . uniqid(), 'unit_type' => 'apartment', 'status' => $status]);
    }

    private function sale(Unit|ProjectPlannedUnit $target, array $overrides = []): array
    {
        return array_merge([
            'listable_type' => $target instanceof Unit ? 'unit' : 'planned_unit',
            'listable_id' => $target->id,
            'listing_type' => 'sale',
            'title' => 'Palm Residence',
            'asking_price' => 100000,
            'currency' => 'USD',
        ], $overrides);
    }

    public function test_creates_sale_listings_for_planned_and_actual_units_using_morph_aliases(): void
    {
        $company = $this->company('a');
        $actor = $this->actor($company, $this->listingPermissions());
        $service = app(ListingService::class);
        $planned = $service->create($actor, $this->sale($this->plannedUnit($company)));
        $actual = $service->create($actor, $this->sale($this->actualUnit($company)));

        $this->assertSame('planned_unit', $planned->getRawOriginal('listable_type'));
        $this->assertSame('unit', $actual->getRawOriginal('listable_type'));
        $this->assertSame(1, $planned->priceHistories()->count());
        $this->assertSame(1, $actual->priceHistories()->count());
    }

    public function test_rejects_cross_company_target_and_invalid_sale_or_rent_data(): void
    {
        $company = $this->company('a');
        $other = $this->company('b');
        $actor = $this->actor($company, $this->listingPermissions());
        $service = app(ListingService::class);

        try { $service->create($actor, $this->sale($this->actualUnit($other))); $this->fail('Cross-company target accepted.'); } catch (ValidationException) { $this->assertTrue(true); }
        try { $service->create($actor, $this->sale($this->actualUnit($company), ['asking_price' => 0])); $this->fail('Zero price accepted.'); } catch (ValidationException) { $this->assertTrue(true); }
        try { $service->create($actor, $this->sale($this->actualUnit($company), ['listing_type' => 'rent', 'rent_frequency' => null])); $this->fail('Rent without frequency accepted.'); } catch (ValidationException) { $this->assertTrue(true); }

        $sale = $service->create($actor, $this->sale($this->actualUnit($company), ['rent_frequency' => 'monthly', 'security_deposit' => 500]));
        $this->assertNull($sale->rent_frequency);
        $this->assertNull($sale->security_deposit);
    }

    public function test_allows_multiple_sale_and_rent_listings_for_the_same_unit(): void
    {
        $company = $this->company('a');
        $actor = $this->actor($company, $this->listingPermissions());
        $target = $this->actualUnit($company);
        $service = app(ListingService::class);
        $service->create($actor, $this->sale($target, ['title' => 'Sale A']));
        $service->create($actor, $this->sale($target, ['title' => 'Sale B', 'asking_price' => 120000]));
        $service->create($actor, $this->sale($target, ['title' => 'Rent A', 'listing_type' => 'rent', 'asking_price' => 1200, 'rent_frequency' => 'monthly']));
        $service->create($actor, $this->sale($target, ['title' => 'Rent B', 'listing_type' => 'rent', 'asking_price' => 1500, 'rent_frequency' => 'yearly']));

        $this->assertSame(4, Listing::withoutGlobalScopes()->where('listable_type', 'unit')->where('listable_id', $target->id)->count());
    }

    public function test_publish_respects_marketability_policy_and_only_allows_valid_transitions(): void
    {
        $company = $this->company('a');
        $actor = $this->actor($company, $this->listingPermissions());
        $service = app(ListingService::class);
        $inactive = $service->create($actor, $this->sale($this->actualUnit($company, 'inactive')));
        try { $service->publish($actor, $inactive); $this->fail('Inactive Unit was published.'); } catch (ValidationException) { $this->assertTrue(true); }

        $maintenance = $service->create($actor, $this->sale($this->actualUnit($company, 'maintenance')));
        try { $service->publish($actor, $maintenance); $this->fail('Maintenance Unit was published without company policy.'); } catch (ValidationException) { $this->assertTrue(true); }
        CompanySetting::withoutGlobalScopes()->create(['company_id' => $company->id, 'allow_maintenance_unit_marketing' => true]);
        $this->assertSame('published', $service->publish($actor, $maintenance)->status->value);
        $this->assertSame('paused', $service->pause($actor, $maintenance)->status->value);
        $this->assertSame('published', $service->publish($actor, $maintenance)->status->value);
        $this->assertSame('archived', $service->archive($actor, $maintenance)->status->value);
        try { $service->publish($actor, $maintenance); $this->fail('Archived Listing was published directly.'); } catch (ValidationException) { $this->assertTrue(true); }
        $this->assertSame('draft', $service->restore($actor, $maintenance)->status->value);
    }

    public function test_price_history_is_atomic_and_only_records_real_pricing_changes(): void
    {
        $company = $this->company('a');
        $actor = $this->actor($company, $this->listingPermissions());
        $service = app(ListingService::class);
        $first = $service->create($actor, $this->sale($this->actualUnit($company), ['title' => 'First']));
        $second = $service->create($actor, $this->sale($this->actualUnit($company), ['title' => 'Second']));

        $service->update($actor, $first, ['title' => 'First revised', 'asking_price' => '100000.00', 'currency' => 'USD', 'listing_type' => 'sale']);
        $this->assertSame(1, $first->priceHistories()->count());
        $service->update($actor, $first, ['title' => 'First revised', 'asking_price' => 110000, 'currency' => 'EUR', 'listing_type' => 'sale', 'price_change_reason' => 'Market review']);
        $this->assertSame(2, $first->fresh()->priceHistories()->count());
        $this->assertSame('100000.00', $first->fresh()->priceHistories()->orderByDesc('id')->first()->old_price);
        $this->assertSame('110000.00', $first->fresh()->asking_price);
        $this->assertSame('100000.00', $second->fresh()->asking_price);
        $this->assertSame(1, $second->priceHistories()->count());
    }

    public function test_super_admin_must_select_the_target_company(): void
    {
        $company = $this->company('a');
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole(\App\Models\Role::platform()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        $superAdmin->givePermissionTo(Permission::findOrCreate('create_listing', 'web'));
        $service = app(ListingService::class);
        $data = $this->sale($this->actualUnit($company));

        try { $service->create($superAdmin, $data); $this->fail('Super Admin company context was optional.'); } catch (ValidationException) { $this->assertTrue(true); }
        $listing = $service->create($superAdmin, $data + ['company_id' => $company->id]);
        $this->assertSame($company->id, $listing->company_id);
    }
    public function test_listing_permissions_are_enforced_server_side(): void
    {
        $company = $this->company('a');
        $target = $this->actualUnit($company);
        $this->expectException(AuthorizationException::class);
        app(ListingService::class)->create($this->actor($company), $this->sale($target));
    }

    public function test_listing_resource_form_and_view_publish_action_follow_the_real_workflow(): void
    {
        $company = $this->company('filament');
        $actor = $this->actor($company, $this->listingPermissions());
        $unit = $this->actualUnit($company);
        $this->actingAs($actor);

        Livewire::test(CreateListing::class)
            ->assertFormFieldExists('listable_type')
            ->assertFormFieldExists('listable_id')
            ->assertFormFieldExists('listing_type')
            ->assertFormFieldExists('asking_price')
            ->fillForm($this->sale($unit, ['title' => 'Filament listing']))
            ->call('create')
            ->assertHasNoFormErrors();

        $listing = Listing::withoutGlobalScopes()->where('title', 'Filament listing')->firstOrFail();

        Livewire::test(ViewListing::class, ['record' => $listing->getRouteKey()])
            ->assertActionVisible('publish')
            ->callAction('publish')
            ->assertHasNoActionErrors();

        $this->assertSame('published', $listing->fresh()->status->value);
    }}
