<?php

namespace Tests\Feature;

use App\Models\{
    AcquisitionParty,
    AcquisitionProperty,
    Company,
    DueDiligenceCase,
    DueDiligenceItem,
    Party,
    PlannedUnitSpecification,
    Project,
    ProjectBuilding,
    ProjectBuildingFloor,
    ProjectMember,
    ProjectPlannedUnit,
    ProjectProperty,
    Property,
    PropertyAcquisition,
    PropertyOwnership,
    User,
};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_one_and_two_record_policies_enforce_role_permissions_and_company_boundaries(): void
    {
        $companyA = Company::create(['name' => 'A', 'email' => 'a@example.test']);
        $companyB = Company::create(['name' => 'B', 'email' => 'b@example.test']);
        $role = Role::findOrCreate('phase_domain_auditor', 'web');

        $matrix = [
            Property::class => ['view_property', 'view_any_property'],
            Party::class => ['view_party', 'view_any_party'],
            PropertyAcquisition::class => ['view_property::acquisition', 'view_any_property::acquisition'],
            AcquisitionProperty::class => ['view_acquisition_property', 'view_any_acquisition_property'],
            AcquisitionParty::class => ['view_acquisition_party', 'view_any_acquisition_party'],
            PropertyOwnership::class => ['view_property_ownership', 'view_any_property_ownership'],
            DueDiligenceCase::class => ['view_due_diligence_case', 'view_any_due_diligence_case'],
            DueDiligenceItem::class => ['view_due_diligence_item', 'view_any_due_diligence_item'],
            Project::class => ['view_project', 'view_any_project'],
            ProjectProperty::class => ['view_project_property', 'view_any_project_property'],
            ProjectMember::class => ['view_project_member', 'view_any_project_member'],
            ProjectBuilding::class => ['view_project_building', 'view_any_project_building'],
            ProjectBuildingFloor::class => ['view_project_building_floor', 'view_any_project_building_floor'],
            ProjectPlannedUnit::class => ['view_project_planned_unit', 'view_any_project_planned_unit'],
            PlannedUnitSpecification::class => ['view_planned_unit_specification', 'view_any_planned_unit_specification'],
        ];

        $permissions = collect($matrix)->flatten()->unique()->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        $role->syncPermissions($permissions);
        $user = User::factory()->create(['company_id' => $companyA->id]);
        $user->assignRole($role);

        foreach ($matrix as $model => [$viewPermission, $viewAnyPermission]) {
            $ownRecord = new $model(['company_id' => $companyA->id]);
            $otherCompanyRecord = new $model(['company_id' => $companyB->id]);

            $this->assertTrue($user->can('viewAny', $model), "{$viewAnyPermission} must authorize {$model} listing.");
            $this->assertTrue($user->can('view', $ownRecord), "{$viewPermission} must authorize its own-company {$model}.");
            $this->assertFalse($user->can('view', $otherCompanyRecord), "{$viewPermission} must not authorize another company's {$model}.");
        }
    }

    public function test_phase_workflow_permissions_are_role_based_and_cannot_cross_company_boundaries(): void
    {
        $companyA = Company::create(['name' => 'A', 'email' => 'a@example.test']);
        $companyB = Company::create(['name' => 'B', 'email' => 'b@example.test']);
        $role = Role::findOrCreate('phase_workflow_operator', 'web');
        $role->syncPermissions(collect([
            'approve_property_acquisition', 'cancel_property_acquisition', 'complete_property_acquisition',
            'approve_project', 'cancel_project', 'close_project',
        ])->map(fn (string $name) => Permission::findOrCreate($name, 'web')));
        $user = User::factory()->create(['company_id' => $companyA->id]);
        $user->assignRole($role);

        $ownAcquisition = new PropertyAcquisition(['company_id' => $companyA->id, 'status' => 'under_due_diligence']);
        $otherAcquisition = new PropertyAcquisition(['company_id' => $companyB->id, 'status' => 'under_due_diligence']);
        $ownProject = new Project(['company_id' => $companyA->id, 'status' => 'planning']);
        $otherProject = new Project(['company_id' => $companyB->id, 'status' => 'planning']);

        foreach (['approve', 'cancel', 'complete'] as $ability) {
            $this->assertTrue($user->can($ability, $ownAcquisition));
            $this->assertFalse($user->can($ability, $otherAcquisition));
        }
        foreach (['approve', 'cancel', 'close'] as $ability) {
            $this->assertTrue($user->can($ability, $ownProject));
            $this->assertFalse($user->can($ability, $otherProject));
        }
    }

    public function test_all_phase_relation_and_workflow_permissions_are_seeded_for_roles_ui(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $expectedCustomPermissions = [
            'approve_property_acquisition', 'cancel_property_acquisition', 'complete_property_acquisition',
            'view_any_acquisition_property', 'view_acquisition_property', 'create_acquisition_property', 'update_acquisition_property', 'delete_acquisition_property',
            'view_any_acquisition_party', 'view_acquisition_party', 'create_acquisition_party', 'update_acquisition_party', 'delete_acquisition_party',
            'view_any_property_ownership', 'view_property_ownership', 'change_property_ownership',
            'view_any_due_diligence_case', 'view_due_diligence_case', 'create_due_diligence_case', 'update_due_diligence_case', 'delete_due_diligence_case', 'clear_due_diligence_case',
            'view_any_due_diligence_item', 'view_due_diligence_item', 'create_due_diligence_item', 'update_due_diligence_item', 'delete_due_diligence_item', 'waive_due_diligence_item',
            'approve_project', 'cancel_project', 'close_project', 'manage_project_members', 'attach_project_property', 'detach_project_property',
            'view_any_project_property', 'view_project_property', 'view_any_project_member', 'view_project_member',
            'view_any_project_building', 'view_project_building', 'create_project_building', 'update_project_building', 'delete_project_building',
            'view_any_project_building_floor', 'view_project_building_floor', 'create_project_building_floor', 'update_project_building_floor', 'delete_project_building_floor',
            'view_any_project_planned_unit', 'view_project_planned_unit', 'create_project_planned_unit', 'update_project_planned_unit', 'delete_project_planned_unit', 'approve_planned_unit', 'cancel_planned_unit',
            'view_any_planned_unit_specification', 'view_planned_unit_specification', 'create_planned_unit_specification', 'update_planned_unit_specification', 'delete_planned_unit_specification',
        ];

        foreach ($expectedCustomPermissions as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }

        $companyAdmin = Role::findByName('company_admin', 'web');
        $this->assertTrue($companyAdmin->hasAllPermissions($expectedCustomPermissions));
    }
}
