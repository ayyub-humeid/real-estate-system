<?php

namespace Tests\Feature;

use App\Filament\Resources\UnitResource\Pages\ListUnits;
use App\Models\Company;
use App\Models\Location;
use App\Models\Project;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnitProjectFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_units_table_can_be_filtered_by_project(): void
    {
        $company = Company::create(['name' => 'Filter Co', 'email' => 'filter@example.test']);
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City', 'type' => 'city']);
        $property = Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Marina', 'address' => 'Addr']);
        $projectA = Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Alpha', 'project_type' => 'residential', 'status' => 'active', 'location_id' => $location->id]);
        $projectB = Project::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Beta', 'project_type' => 'residential', 'status' => 'active', 'location_id' => $location->id]);

        $inA = Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'project_id' => $projectA->id, 'unit_number' => 'A-1', 'status' => 'draft']);
        $inB = Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'project_id' => $projectB->id, 'unit_number' => 'B-1', 'status' => 'draft']);

        $user = User::factory()->create(['company_id' => $company->id]);
        foreach (['view_any_unit', 'view_unit'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user);

        Livewire::test(ListUnits::class)
            ->filterTable('project', $projectA->id)
            ->assertCanSeeTableRecords([$inA])
            ->assertCanNotSeeTableRecords([$inB]);
    }
}
