<?php

namespace Tests\Feature;

use App\Models\{Company,Location,Project,ProjectBuildingFloor,Property,User};
use App\Services\ProjectPlanningService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectPlanningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions, bool $superAdmin = false): User
    {
        $user = User::factory()->create(['company_id' => $superAdmin ? null : $company->id]);
        if ($superAdmin) $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        return $user;
    }

    private function property(Company $company): Property
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => "City {$company->id}", 'type' => 'city']);
        return Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => "Land {$company->id}", 'address' => 'Address']);
    }

    private function planningPermissions(): array
    {
        return [
            'create_project', 'update_project', 'approve_project', 'cancel_project', 'close_project',
            'attach_project_property', 'detach_project_property', 'manage_project_members',
            'create_project_building', 'update_project_building', 'delete_project_building',
            'create_project_building_floor', 'update_project_building_floor', 'delete_project_building_floor',
            'create_project_planned_unit', 'update_project_planned_unit', 'delete_project_planned_unit',
            'create_planned_unit_specification', 'update_planned_unit_specification', 'delete_planned_unit_specification',
            'approve_planned_unit', 'cancel_planned_unit',
        ];
    }

    public function test_project_planning_hierarchy_uses_planned_units_without_creating_actual_units(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, $this->planningPermissions());
        $service = app(ProjectPlanningService::class);
        $project = $service->createProject($actor, ['name' => 'Aga Heights', 'project_type' => 'residential']);
        $property = $this->property($company);
        $service->attachProperty($actor, $project, $property, ['role' => 'primary_land']);
        $member = $this->actor($company, []);
        $service->assignMember($actor, $project, $member, ['role' => 'project_manager']);
        $building = $service->createBuilding($actor, $project, ['name' => 'Tower A', 'code' => 'A']);
        $floor = $service->createFloor($actor, $building, ['floor_number' => 2, 'label' => 'Second floor']);
        $unit = $service->createPlannedUnit($actor, $floor, ['code' => 'A-201', 'unit_type' => 'apartment', 'planned_area' => 120]);
        $specification = $service->createSpecification($actor, $unit, ['name' => 'Bedrooms', 'value' => '3']);

        $this->assertSame('planning', $project->status);
        $this->assertSame($company->id, $building->company_id);
        $this->assertSame($building->id, $floor->project_building_id);
        $this->assertSame($floor->id, $unit->project_building_floor_id);
        $this->assertSame($unit->id, $specification->project_planned_unit_id);
        $this->assertSame(0, \App\Models\Unit::withoutGlobalScopes()->count());
    }

    public function test_cross_company_property_and_member_are_rejected(): void
    {
        $companyA = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $companyB = Company::create(['name' => 'B', 'email' => 'b@test.com']);
        $actor = $this->actor($companyA, $this->planningPermissions());
        $project = app(ProjectPlanningService::class)->createProject($actor, ['name' => 'Aga', 'project_type' => 'residential']);
        $service = app(ProjectPlanningService::class);

        try { $service->attachProperty($actor, $project, $this->property($companyB)); $this->fail('Cross-company property was accepted.'); } catch (ValidationException) { $this->assertSame(0, $project->projectProperties()->count()); }
        try { $service->assignMember($actor, $project, $this->actor($companyB, []), ['role' => 'engineer']); $this->fail('Cross-company member was accepted.'); } catch (ValidationException) { $this->assertSame(0, $project->members()->count()); }
    }

    public function test_unauthorized_user_and_super_admin_company_context_are_enforced(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $service = app(ProjectPlanningService::class);
        $this->expectException(AuthorizationException::class);
        $service->createProject($this->actor($company, []), ['name' => 'Denied', 'project_type' => 'residential']);
    }

    public function test_super_admin_must_select_company_and_can_create_for_selected_company(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $superAdmin = $this->actor($company, ['create_project'], true);
        $service = app(ProjectPlanningService::class);
        try { $service->createProject($superAdmin, ['name' => 'Missing', 'project_type' => 'residential']); $this->fail('Super Admin company context was optional.'); } catch (ValidationException) { $this->assertTrue(true); }
        $project = $service->createProject($superAdmin, ['company_id' => $company->id, 'name' => 'Selected', 'project_type' => 'residential']);
        $this->assertSame($company->id, $project->company_id);
    }

    public function test_project_and_planned_unit_status_rules_are_enforced(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, $this->planningPermissions());
        $service = app(ProjectPlanningService::class);
        $project = $service->createProject($actor, ['name' => 'Aga', 'project_type' => 'residential']);
        try { $service->transitionProject($actor, $project, 'approved'); $this->fail('Project approved without property.'); } catch (ValidationException) { $this->assertSame('planning', $project->fresh()->status); }
        $service->attachProperty($actor, $project, $this->property($company));
        $service->transitionProject($actor, $project, 'approved');
        $building = $service->createBuilding($actor, $project->fresh(), ['name' => 'Tower']);
        $floor = $service->createFloor($actor, $building, ['floor_number' => 1]);
        $unit = $service->createPlannedUnit($actor, $floor, ['code' => 'T-101', 'unit_type' => 'apartment']);
        $this->assertSame('approved', $service->transitionPlannedUnit($actor, $unit, 'approved')->status);
        $this->assertSame('cancelled', $service->transitionPlannedUnit($actor, $unit->fresh(), 'cancelled')->status);
        $service->transitionProject($actor, $project->fresh(), 'in_progress');
        $service->transitionProject($actor, $project->fresh(), 'completed');
        $service->transitionProject($actor, $project->fresh(), 'closed');
        $this->expectException(ValidationException::class);
        $service->createBuilding($actor, $project->fresh(), ['name' => 'Blocked']);
    }

    public function test_floor_number_is_unique_per_building(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, $this->planningPermissions());
        $service = app(ProjectPlanningService::class);
        $project = $service->createProject($actor, ['name' => 'Aga', 'project_type' => 'residential']);
        $building = $service->createBuilding($actor, $project, ['name' => 'Tower']);
        $service->createFloor($actor, $building, ['floor_number' => 0]);
        $this->expectException(QueryException::class);
        $service->createFloor($actor, $building, ['floor_number' => 0]);
    }

    public function test_planning_records_can_be_edited_safely_and_children_must_be_removed_before_parents(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, $this->planningPermissions());
        $service = app(ProjectPlanningService::class);
        $project = $service->createProject($actor, ['name' => 'Aga', 'project_type' => 'residential']);
        $building = $service->createBuilding($actor, $project, ['name' => 'Tower A', 'code' => 'A']);
        $floor = $service->createFloor($actor, $building, ['floor_number' => 1, 'label' => 'First']);
        $unit = $service->createPlannedUnit($actor, $floor, ['code' => 'A-101', 'unit_type' => 'apartment']);
        $specification = $service->createSpecification($actor, $unit, ['name' => 'Bedrooms', 'value' => '2']);

        $this->assertSame('Tower A revised', $service->updateBuilding($actor, $building, ['name' => 'Tower A revised'])->name);
        $this->assertSame(2, $service->updateFloor($actor, $floor, ['floor_number' => 2])->floor_number);
        $this->assertSame('A-201', $service->updatePlannedUnit($actor, $unit, ['code' => 'A-201'])->code);
        $this->assertSame('3', $service->updateSpecification($actor, $specification, ['value' => '3'])->value);

        try { $service->deletePlannedUnit($actor, $unit->fresh()); $this->fail('A unit with specifications was deleted.'); } catch (ValidationException) { $this->assertDatabaseCount('project_planned_units', 1); }
        try { $service->deleteFloor($actor, $floor->fresh()); $this->fail('A floor with planned units was deleted.'); } catch (ValidationException) { $this->assertDatabaseCount('project_building_floors', 1); }
        try { $service->deleteBuilding($actor, $building->fresh()); $this->fail('A building with floors was deleted.'); } catch (ValidationException) { $this->assertDatabaseCount('project_buildings', 1); }

        $service->deleteSpecification($actor, $specification);
        $service->deletePlannedUnit($actor, $unit->fresh());
        $service->deleteFloor($actor, $floor->fresh());
        $service->deleteBuilding($actor, $building->fresh());
        $this->assertDatabaseCount('project_buildings', 0);
    }

    public function test_building_and_planned_unit_types_use_the_controlled_catalogue(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $actor = $this->actor($company, $this->planningPermissions());
        $service = app(ProjectPlanningService::class);
        $project = $service->createProject($actor, ['name' => 'Aga', 'project_type' => 'residential']);

        try { $service->createBuilding($actor, $project, ['name' => 'Invalid', 'building_type' => 'towerish']); $this->fail('An unsupported building type was accepted.'); } catch (ValidationException) { $this->assertDatabaseCount('project_buildings', 0); }
        $building = $service->createBuilding($actor, $project, ['name' => 'Tower', 'building_type' => 'residential_tower']);
        $floor = $service->createFloor($actor, $building, ['floor_number' => 1]);
        try { $service->createPlannedUnit($actor, $floor, ['code' => 'T-101', 'unit_type' => 'towerish']); $this->fail('An unsupported unit type was accepted.'); } catch (ValidationException) { $this->assertDatabaseCount('project_planned_units', 0); }

        $unit = $service->createPlannedUnit($actor, $floor, ['code' => 'T-101', 'unit_type' => 'apartment']);
        $this->assertSame('apartment', $unit->unit_type);
    }
}
