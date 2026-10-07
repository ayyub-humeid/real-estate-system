<?php

namespace Tests\Feature;

use App\Filament\Resources\UnitResource\Pages\CreateUnit;
use App\Filament\Resources\UnitResource\Pages\EditUnit;
use App\Filament\Resources\UnitResource\Pages\ListUnits;
use App\Models\Company;
use App\Models\Location;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnitSetupFilamentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);

        foreach (['view_any_unit', 'view_unit', 'create_unit', 'update_unit', 'change_unit_status'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function property(Company $company): Property
    {
        $location = Location::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => "City {$company->id}",
            'type' => 'city',
        ]);

        return Property::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'location_id' => $location->id,
            'name' => "Property {$company->id}",
            'address' => 'Test address',
        ]);
    }

    public function test_unit_create_form_exposes_physical_setup_fields_and_locks_status(): void
    {
        $company = Company::create(['name' => 'Unit UI Company', 'email' => 'unit-ui@example.test']);
        $actor = $this->actor($company);
        $property = $this->property($company);
        $this->actingAs($actor);

        Livewire::test(CreateUnit::class)
            ->assertFormFieldExists('property_id')
            ->assertFormFieldExists('actual_area')
            ->assertFormFieldExists('area_unit')
            ->assertFormFieldIsDisabled('status')
            ->fillForm([
                'property_id' => $property->id,
                'unit_number' => 'UI-101',
                'type' => 'apartment',
                'rent_price' => 0,
                'actual_area' => 95,
                'area_unit' => 'm2',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('units', [
            'company_id' => $company->id,
            'property_id' => $property->id,
            'unit_number' => 'UI-101',
            'status' => 'draft',
        ]);
    }

    public function test_user_completes_create_transition_and_edit_workflow_through_filament(): void
    {
        $company = Company::create(['name' => 'Unit Flow Company', 'email' => 'unit-flow@example.test']);
        $actor = $this->actor($company);
        $property = $this->property($company);
        $this->actingAs($actor);

        Livewire::test(CreateUnit::class)
            ->fillForm([
                'property_id' => $property->id,
                'unit_number' => 'FLOW-101',
                'type' => 'apartment',
                'rent_price' => 0,
                'actual_area' => 100,
                'area_unit' => 'm2',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $unit = Unit::withoutGlobalScopes()->where('unit_number', 'FLOW-101')->firstOrFail();

        Livewire::test(ListUnits::class)
            ->callTableAction('changeStatus', $unit, data: ['status' => 'ready'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('unit_status_histories', [
            'unit_id' => $unit->id,
            'from_status' => 'draft',
            'to_status' => 'ready',
            'changed_by' => $actor->id,
        ]);

        Livewire::test(EditUnit::class, ['record' => $unit->getRouteKey()])
            ->assertFormFieldIsDisabled('status')
            ->fillForm(['actual_area' => 102])
            ->call('save')
            ->assertHasNoFormErrors();

        $unit->refresh();
        $this->assertSame('ready', $unit->status);
        $this->assertSame(102.0, (float) $unit->actual_area);
    }
}
