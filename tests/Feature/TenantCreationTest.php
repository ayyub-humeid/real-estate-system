<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Tenant;
use App\Filament\Resources\TenantResource\Pages\CreateTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'company_admin']);
        }

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return true;
        });
    }

    public function test_super_admin_can_create_tenant_with_company_id()
    {
        $company = Company::create([
            'name' => 'Test Company',
            'email' => 'test@company.com',
        ]);

        // Create a super admin user
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'company_id' => $company->id,
        ]);

        // If system uses Spatie roles strictly:
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $superAdmin->assignRole('super_admin');
        }

        $this->actingAs($superAdmin);

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'company_id' => $company->id,
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'johndoe@example.com',
                    'phone' => '1234567890',
                    'password' => 'password123',
                ],
                'status' => 'active',
                'move_in_date' => now()->format('Y-m-d'),
                'number_of_occupants' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@example.com',
            'company_id' => $company->id,
            'role' => 'tenant',
        ]);

        $this->assertDatabaseHas('tenants', [
            'company_id' => $company->id,
        ]);
    }

    public function test_company_admin_can_create_tenant_without_explicit_company_id()
    {
        $company = Company::create([
            'name' => 'Test Company 2',
            'email' => 'test2@company.com',
        ]);

        // Create a company admin user assigned to the company
        $companyAdmin = User::factory()->create([
            'role' => 'company_admin',
            'company_id' => $company->id,
        ]);

        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $companyAdmin->assignRole('company_admin');
        }

        $this->actingAs($companyAdmin);

        Livewire::test(CreateTenant::class)
            ->fillForm([
                // Company admins don't see the company_id field in the form
                'user' => [
                    'name' => 'Jane Doe',
                    'email' => 'janedoe@example.com',
                    'phone' => '0987654321',
                    'password' => 'password123',
                ],
                'status' => 'active',
                'move_in_date' => now()->format('Y-m-d'),
                'number_of_occupants' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'janedoe@example.com',
            'company_id' => $company->id,
            'role' => 'tenant',
        ]);

        $this->assertDatabaseHas('tenants', [
            'company_id' => $company->id,
        ]);
    }
}
