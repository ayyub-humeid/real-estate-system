<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Models\{Company, Role, User};
use App\Services\CompanyRoleService;
use App\Support\StandardRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StandardRolesTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name = 'Roles Co'): Company
    {
        return Company::create(['name' => $name, 'email' => str($name)->slug().'@example.test']);
    }

    private function seedAllTemplatePermissions(): void
    {
        foreach (collect(StandardRoles::all())->flatten()->unique() as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /** A typo in a template would silently give a role less access, so every name must be checked by real code. */
    public function test_every_template_permission_is_a_real_permission_string_in_the_code(): void
    {
        $source = collect(['app/Policies', 'app/Filament', 'app/Services', 'database/seeders'])
            ->flatMap(fn (string $dir) => \Illuminate\Support\Facades\File::allFiles(base_path($dir)))
            ->map(fn ($file) => file_get_contents($file->getPathname()))
            ->implode("\n");

        $unknown = collect(StandardRoles::all())->flatten()->unique()
            ->reject(fn (string $name): bool => str_contains($source, "'{$name}'"));

        $this->assertSame([], $unknown->values()->all(), 'These template permissions are not used anywhere in the code.');
    }

    public function test_installing_creates_the_roles_for_that_company_only(): void
    {
        $this->seedAllTemplatePermissions();
        $company = $this->company();
        $other = $this->company('Other Co');

        $result = app(CompanyRoleService::class)->installStandardRoles($company);

        $this->assertEqualsCanonicalizing(StandardRoles::names(), $result['created']);
        $agent = Role::forCompany($company->id)->where('name', 'sales_agent')->firstOrFail();
        $manager = Role::forCompany($company->id)->where('name', 'sales_manager')->firstOrFail();
        $this->assertTrue($agent->hasPermissionTo('create_lead'));
        $this->assertFalse($agent->hasPermissionTo('approve_and_activate_reservation'));
        $this->assertTrue($manager->hasPermissionTo('approve_and_activate_reservation'));
        $this->assertTrue($manager->hasPermissionTo('create_contract_from_reservation'));
        $this->assertSame(0, Role::forCompany($other->id)->count());
    }

    public function test_installing_again_never_changes_a_role_the_company_customized(): void
    {
        $this->seedAllTemplatePermissions();
        $company = $this->company();
        $service = app(CompanyRoleService::class);
        $service->installStandardRoles($company);

        $agent = Role::forCompany($company->id)->where('name', 'sales_agent')->firstOrFail();
        $agent->revokePermissionTo('create_lead');

        $second = $service->installStandardRoles($company);

        $this->assertSame([], $second['created']);
        $this->assertEqualsCanonicalizing(StandardRoles::names(), $second['skipped']);
        $this->assertFalse($agent->fresh()->hasPermissionTo('create_lead'));
    }

    public function test_roles_are_still_created_when_the_permissions_have_not_been_seeded_yet(): void
    {
        $result = app(CompanyRoleService::class)->installStandardRoles($company = $this->company());

        $this->assertEqualsCanonicalizing(StandardRoles::names(), $result['created']);
        $this->assertGreaterThan(0, $result['missing_permissions']);
        $this->assertSame(0, Role::forCompany($company->id)->where('name', 'sales_agent')->firstOrFail()->permissions()->count());
    }

    public function test_the_roles_page_offers_the_action_and_installs_the_roles(): void
    {
        $this->seedAllTemplatePermissions();
        $company = $this->company();
        $admin = User::factory()->create(['company_id' => $company->id]);
        foreach (['view_any_role', 'view_role', 'create_role'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($admin);

        Livewire::test(ListRoles::class)
            ->assertSuccessful()
            ->assertActionVisible('installStandardRoles')
            ->callAction('installStandardRoles')
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(count(StandardRoles::names()), Role::forCompany($company->id)->whereIn('name', StandardRoles::names())->count());

        // Running it again explains that nothing changed instead of failing silently.
        Livewire::test(ListRoles::class)
            ->callAction('installStandardRoles')
            ->assertNotified(__('roles.standard.nothing_new'));
    }

    public function test_the_action_is_hidden_without_permission_to_create_roles(): void
    {
        $viewer = User::factory()->create(['company_id' => $this->company()->id]);
        foreach (['view_any_role', 'view_role'] as $permission) {
            $viewer->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($viewer);

        Livewire::test(ListRoles::class)->assertSuccessful()->assertActionHidden('installStandardRoles');
    }
}
