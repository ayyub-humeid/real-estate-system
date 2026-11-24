<?php

namespace Tests\Feature;

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

class UnitNotesTest extends TestCase
{
    use RefreshDatabase;

    private function unit(Company $company): Unit
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City', 'type' => 'city']);
        $property = Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Marina', 'address' => 'Addr']);

        return Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-1', 'status' => 'draft']);
    }

    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_a_user_who_can_update_saves_notes_from_the_table_action(): void
    {
        $company = Company::create(['name' => 'Notes Co', 'email' => 'notes@example.test']);
        $unit = $this->unit($company);
        $this->actingAs($this->user($company, ['view_any_unit', 'view_unit', 'update_unit']));

        Livewire::test(ListUnits::class)
            ->callTableAction('notes', $unit, ['notes' => 'Corner unit, sea view'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Corner unit, sea view', $unit->fresh()->notes);
    }

    public function test_a_read_only_user_cannot_change_notes(): void
    {
        $company = Company::create(['name' => 'Notes RO Co', 'email' => 'notes-ro@example.test']);
        $unit = $this->unit($company);
        $unit->update(['notes' => 'Original']);
        $this->actingAs($this->user($company, ['view_any_unit', 'view_unit']));

        Livewire::test(ListUnits::class)
            ->callTableAction('notes', $unit, ['notes' => 'Hacked']);

        $this->assertSame('Original', $unit->fresh()->notes);
    }
}
