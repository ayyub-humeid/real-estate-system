<?php

namespace Tests\Feature;

use App\Filament\Resources\PartyResource\Pages\CreateParty;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartyCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'company_admin']);

        \Illuminate\Support\Facades\Gate::before(fn (): bool => true);
    }

    public function test_super_admin_must_select_a_company_when_creating_a_party(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);

        Livewire::test(CreateParty::class)
            ->fillForm(['type' => 'individual', 'name' => 'Seller'])
            ->call('create')
            ->assertHasFormErrors(['company_id' => 'required']);
    }

    public function test_super_admin_creates_a_party_for_the_selected_company(): void
    {
        $company = Company::create(['name' => 'Selected Company', 'email' => 'selected@example.test']);
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);

        Livewire::test(CreateParty::class)
            ->fillForm(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Seller'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('parties', [
            'company_id' => $company->id,
            'name' => 'Seller',
        ]);
    }

    public function test_company_admin_cannot_inject_another_company_when_creating_a_party(): void
    {
        $company = Company::create(['name' => 'Company A', 'email' => 'a@example.test']);
        $otherCompany = Company::create(['name' => 'Company B', 'email' => 'b@example.test']);
        $companyAdmin = User::factory()->create(['company_id' => $company->id]);
        $companyAdmin->assignRole('company_admin');

        $this->actingAs($companyAdmin);

        Livewire::test(CreateParty::class)
            ->fillForm(['company_id' => $otherCompany->id, 'type' => 'individual', 'name' => 'Scoped Seller'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('parties', [
            'company_id' => $company->id,
            'name' => 'Scoped Seller',
        ]);
        $this->assertDatabaseMissing('parties', [
            'company_id' => $otherCompany->id,
            'name' => 'Scoped Seller',
        ]);
    }
}
