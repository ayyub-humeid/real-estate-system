<?php

namespace Tests\Feature;

use App\Models\{Company, Lease, Location, Party, Project, ProjectBuilding, ProjectBuildingFloor, ProjectPlannedUnit, ProjectProperty, Property, Tenant, Unit, User};
use App\Services\UnitSetupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnitSetupWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        return $user;
    }
    private function property(Company $company): Property
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => "City {$company->id}", 'type' => 'city']);
        return Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => "Property {$company->id}", 'address' => 'Address']);
    }
    private function permissions(): array { return ['create_unit', 'update_unit', 'change_unit_status', 'reactivate_unit', 'change_unit_ownership', 'create_actual_unit_from_planned_unit']; }

    public function test_manual_unit_is_physical_and_status_transitions_are_audited(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@example.test']);
        $actor = $this->actor($company, $this->permissions());
        $service = app(UnitSetupService::class);
        $unit = $service->create($actor, ['property_id' => $this->property($company)->id, 'unit_number' => 'A-1', 'type' => 'apartment', 'rent_price' => 0, 'actual_area' => 100, 'area_unit' => 'm2']);
        $this->assertSame('draft', $unit->status);
        $unit = $service->transitionStatus($actor, $unit, 'ready');
        $this->assertSame('ready', $unit->status);
        $this->assertDatabaseHas('unit_status_histories', ['unit_id' => $unit->id, 'from_status' => 'draft', 'to_status' => 'ready']);
        try { $service->transitionStatus($actor, $unit, 'draft'); $this->fail('Invalid transition accepted.'); } catch (ValidationException) { $this->assertTrue(true); }
    }

    public function test_planned_unit_converts_once_and_cross_company_parent_is_rejected(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@example.test']);
        $other = Company::create(['name' => 'B', 'email' => 'b@example.test']);
        $actor = $this->actor($company, $this->permissions());
        $property = $this->property($company);
        $project = Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'P', 'project_type' => 'residential', 'currency' => 'USD', 'status' => 'in_progress']);
        ProjectProperty::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_id' => $project->id, 'property_id' => $property->id, 'attached_at' => now()]);
        $building = ProjectBuilding::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_id' => $project->id, 'name' => 'Tower']);
        $floor = ProjectBuildingFloor::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_building_id' => $building->id, 'floor_number' => 1]);
        $planned = ProjectPlannedUnit::withoutGlobalScopes()->create(['company_id' => $company->id, 'project_building_floor_id' => $floor->id, 'code' => 'A-101', 'unit_type' => 'apartment', 'planned_area' => 100, 'status' => 'approved']);
        $service = app(UnitSetupService::class);
        $unit = $service->convertPlannedUnit($actor, $planned, ['property_id' => $property->id, 'unit_number' => 'A-101', 'type' => 'apartment', 'rent_price' => 0, 'actual_area' => 102, 'area_unit' => 'm2']);
        $this->assertSame($planned->id, $unit->planned_unit_id);
        $this->assertSame('converted', $planned->fresh()->status);
        try { $service->convertPlannedUnit($actor, $planned->fresh(), ['property_id' => $property->id, 'unit_number' => 'Duplicate', 'type' => 'apartment', 'actual_area' => 100, 'area_unit' => 'm2']); $this->fail('Duplicate conversion accepted.'); } catch (ValidationException) { $this->assertTrue(true); }
        try { $service->create($actor, ['property_id' => $this->property($other)->id, 'unit_number' => 'B-1', 'type' => 'apartment']); $this->fail('Cross-company unit accepted.'); } catch (AuthorizationException) { $this->assertTrue(true); }
    }

    public function test_ownership_history_and_lease_derived_tenancy_do_not_change_physical_status(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@example.test']);
        $actor = $this->actor($company, $this->permissions());
        $service = app(UnitSetupService::class);
        $property = $this->property($company);
        $unit = $service->create($actor, ['property_id' => $property->id, 'unit_number' => 'A-1', 'type' => 'apartment', 'rent_price' => 100, 'actual_area' => 100, 'area_unit' => 'm2']);
        $unit = $service->transitionStatus($actor, $unit, 'ready');
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'company', 'name' => 'Owner', 'is_active' => true]);
        $service->replaceOwnership($actor, $unit, [['party_id' => $party->id, 'ownership_percentage' => 100]], now()->toDateString());
        $this->assertSame(1, $unit->activeOwnerships()->count());
        $tenantUser = User::factory()->create(['company_id' => $company->id]);
        $tenant = Tenant::withoutGlobalScopes()->create(['company_id' => $company->id, 'user_id' => $tenantUser->id, 'status' => 'active']);
        Lease::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'start_date' => now(), 'rent_amount' => 100, 'payment_frequency' => 'monthly', 'payment_day' => 1, 'status' => 'active']);
        $this->assertSame('occupied', $unit->fresh()->tenancy_state);
        $this->assertSame('ready', $unit->fresh()->status);
    }
}
