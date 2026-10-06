<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\CompanyRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompanyScopedRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_companies_can_use_the_same_role_name_without_sharing_the_role(): void
    {
        [$companyA, $companyB] = [$this->company('A'), $this->company('B')];

        $roleA = Role::create(['company_id' => $companyA->id, 'name' => 'leasing_manager', 'guard_name' => 'web']);
        $roleB = Role::create(['company_id' => $companyB->id, 'name' => 'leasing_manager', 'guard_name' => 'web']);

        $this->assertNotSame($roleA->id, $roleB->id);
        $this->assertSame($companyA->id, $roleA->company_id);
        $this->assertSame($companyB->id, $roleB->company_id);
    }

    public function test_company_admin_can_only_manage_its_own_roles_and_cannot_grant_platform_permissions(): void
    {
        Permission::findOrCreate('view_property', 'web');
        Permission::findOrCreate('view_company', 'web');
        Permission::findOrCreate('view_company::setting', 'web');
        Permission::findOrCreate('view_user', 'web');

        $companyA = $this->company('A');
        $companyB = $this->company('B');
        $admin = User::factory()->create(['company_id' => $companyA->id, 'role' => 'company_admin']);
        $roleA = Role::create(['company_id' => $companyA->id, 'name' => 'leasing_manager', 'guard_name' => 'web']);
        $roleB = Role::create(['company_id' => $companyB->id, 'name' => 'leasing_manager', 'guard_name' => 'web']);
        $service = app(CompanyRoleService::class);

        $service->syncPermissions($admin, $roleA, ['view_property']);
        $this->assertTrue($roleA->fresh()->hasPermissionTo('view_property'));

        $service->syncPermissions($admin, $roleA, ['view_company::setting']);
        $this->assertTrue($roleA->fresh()->hasPermissionTo('view_company::setting'));

        try {
            $service->syncPermissions($admin, $roleB, ['view_property']);
            $this->fail('A company admin managed another company role.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $service->syncPermissions($admin, $roleA, ['view_company']);
            $this->fail('A company role received a platform permission.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $service->syncPermissions($admin, $roleA, ['view_user']);
    }

    public function test_default_roles_are_provisioned_per_company_and_assignment_uses_the_company_copy(): void
    {
        $companyA = $this->company('A');
        $companyB = $this->company('B');
        $userA = User::factory()->create(['company_id' => $companyA->id, 'role' => 'property_manager']);
        $userB = User::factory()->create(['company_id' => $companyB->id, 'role' => 'property_manager']);

        $roleA = $userA->roles()->sole();
        $roleB = $userB->roles()->sole();

        $this->assertSame($companyA->id, $roleA->company_id);
        $this->assertSame($companyB->id, $roleB->company_id);
        $this->assertNotSame($roleA->id, $roleB->id);
    }

    public function test_release_sync_adds_new_template_permissions_to_existing_default_company_roles_only(): void
    {
        $company = $this->company('A');
        $template = Role::platform()->firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
        $newPermission = Permission::findOrCreate('create_project_planned_unit', 'web');
        $template->givePermissionTo($newPermission);

        $companyAdmin = Role::create([
            'company_id' => $company->id,
            'name' => 'company_admin',
            'guard_name' => 'web',
        ]);
        $companyAdmin->givePermissionTo(Permission::findOrCreate('view_project', 'web'));

        $customRole = Role::create([
            'company_id' => $company->id,
            'name' => 'custom_planner',
            'guard_name' => 'web',
        ]);

        $added = app(CompanyRoleService::class)->syncNewDefaultPermissions($company);

        $this->assertGreaterThanOrEqual(1, $added);
        $this->assertTrue($companyAdmin->fresh()->hasPermissionTo($newPermission));
        $this->assertFalse($customRole->fresh()->hasPermissionTo($newPermission));
    }

    public function test_exact_role_assignment_uses_the_selected_role_id_and_rejects_another_company_role(): void
    {
        $companyA = $this->company('A');
        $companyB = $this->company('B');
        $roleA = Role::create(['company_id' => $companyA->id, 'name' => 'leasing_manager', 'guard_name' => 'web']);
        $roleB = Role::create(['company_id' => $companyB->id, 'name' => 'leasing_manager', 'guard_name' => 'web']);
        $user = User::factory()->create(['company_id' => $companyA->id, 'role' => null]);
        $service = app(CompanyRoleService::class);

        $service->assignRole($user, $roleA);
        $this->assertSame($roleA->id, $user->fresh()->roles()->sole()->id);
        $this->assertSame('leasing_manager', $user->fresh()->role);

        $this->expectException(ValidationException::class);
        $service->assignRole($user, $roleB);
    }

    public function test_company_admin_without_company_context_cannot_access_platform_roles(): void
    {
        $companyAdmin = Role::platform()->firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['company_id' => null, 'role' => null]);
        $user->syncRoles([$companyAdmin]); // Simulates a legacy/incomplete record.

        $policy = app(RolePolicy::class);
        $this->assertFalse($policy->view($user, $companyAdmin));
        $this->assertFalse($policy->create($user));

        $this->expectException(ValidationException::class);
        app(CompanyRoleService::class)->assertRoleManageable($user, $companyAdmin);
    }

    private function company(string $name): Company
    {
        return Company::create([
            'name' => "Company {$name}",
            'email' => strtolower($name).'@company.test',
        ]);
    }
}
