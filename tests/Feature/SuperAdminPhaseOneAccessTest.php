<?php

namespace Tests\Feature;

use App\Filament\Resources\PartyResource;
use App\Filament\Resources\PropertyAcquisitionResource;
use App\Filament\Resources\PropertyResource\Pages\ViewProperty;
use App\Filament\Resources\PropertyResource\RelationManagers\OwnershipsRelationManager;
use App\Models\Party;
use App\Models\Property;
use App\Models\PropertyAcquisition;
use App\Models\PropertyOwnership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminPhaseOneAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_requires_the_assigned_phase_one_permissions(): void
    {
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->assertFalse($superAdmin->can('viewAny', PropertyAcquisition::class));

        foreach (['view_any_property_acquisition', 'view_property_acquisition', 'update_property_acquisition'] as $name) {
            $superAdmin->givePermissionTo(Permission::create(['name' => $name, 'guard_name' => 'web']));
        }

        $superAdmin->refresh();
        $this->assertTrue($superAdmin->can('viewAny', PropertyAcquisition::class));
        $this->assertTrue($superAdmin->can('view', new PropertyAcquisition(['company_id' => 999])));
        $this->assertTrue($superAdmin->can('update', new PropertyAcquisition(['company_id' => 999])));

        $this->actingAs($superAdmin);

        $this->assertTrue(PropertyAcquisitionResource::canViewAny());
    }
}
