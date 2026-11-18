<?php

namespace Tests\Feature;

use App\Filament\Resources\PropertyResource\Pages\ListProperties;
use App\Filament\Resources\UnitResource\Pages\ListUnits;
use App\Models\{AcquisitionParty, Company, Lease, Location, MaintenanceRequest, Party, Property, PropertyAcquisition, Tenant, Unit, User};
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SafeDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'company_admin']);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function property(Company $company): Property
    {
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City ' . uniqid(), 'type' => 'city']);

        return Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'P ' . uniqid(), 'address' => 'Address']);
    }

    private function unit(Property $property): Unit
    {
        return Unit::withoutGlobalScopes()->create([
            'company_id' => $property->company_id, 'property_id' => $property->id,
            'unit_number' => 'U-' . uniqid(), 'type' => 'apartment', 'status' => 'draft',
        ]);
    }

    private function assertDeleteRejected(object $record): void
    {
        try {
            $record->delete();
            $this->fail(class_basename($record) . ' with history must not be deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('record', $exception->errors());
        }

        $this->assertTrue($record::withoutGlobalScopes()->whereKey($record->getKey())->exists());
    }

    public function test_unit_bulk_delete_keeps_units_with_history(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $user = $this->actor($company, ['view_any_unit', 'view_unit', 'delete_unit', 'delete_any_unit']);
        $this->actingAs($user);

        $property = $this->property($company);
        $clean = $this->unit($property);
        $withHistory = $this->unit($property);
        MaintenanceRequest::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'unit_id' => $withHistory->id, 'reported_by_id' => $user->id,
            'title' => 'Leak', 'description' => 'Kitchen', 'status' => 'new', 'priority' => 'low',
        ]);

        Livewire::test(ListUnits::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$clean, $withHistory])
            ->assertNotified(__('deletion.bulk_partial_title'));

        $this->assertFalse(Unit::withoutGlobalScopes()->whereKey($clean->id)->exists());
        $this->assertTrue(Unit::withoutGlobalScopes()->whereKey($withHistory->id)->exists());
        $this->assertSame(1, MaintenanceRequest::withoutGlobalScopes()->count());
    }

    public function test_property_delete_action_explains_blockers_and_keeps_units(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $this->actingAs($this->actor($company, ['view_any_property', 'view_property', 'delete_property']));
        $property = $this->property($company);
        $this->unit($property);

        Livewire::test(ListProperties::class)
            ->callTableAction('delete', $property)
            ->assertNotified(__('deletion.blocked_title'));

        $this->assertTrue(Property::withoutGlobalScopes()->whereKey($property->id)->exists());
        $this->assertSame(1, Unit::withoutGlobalScopes()->count());
    }

    public function test_company_with_business_data_cannot_be_deleted(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $this->property($company);

        $this->assertSame([__('deletion.blockers.properties')], $company->deletionBlockers());
        $this->assertDeleteRejected($company);

        $empty = Company::create(['name' => 'B', 'email' => 'b@test.com']);
        $empty->delete();
        $this->assertFalse(Company::whereKey($empty->id)->exists());
    }

    public function test_tenant_user_with_a_lease_cannot_be_deleted(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $tenantUser = User::factory()->create(['company_id' => $company->id]);
        $tenant = Tenant::withoutGlobalScopes()->create(['company_id' => $company->id, 'user_id' => $tenantUser->id, 'status' => 'active']);
        $property = $this->property($company);
        Lease::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'property_id' => $property->id, 'unit_id' => $this->unit($property)->id,
            'tenant_id' => $tenant->id, 'start_date' => now(), 'rent_amount' => 500, 'payment_frequency' => 'monthly', 'status' => 'active',
        ]);

        $this->assertDeleteRejected($tenantUser);
        $this->assertSame(1, Lease::withoutGlobalScopes()->count());
    }

    public function test_parties_of_a_closed_acquisition_cannot_be_removed(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Seller']);

        $open = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'cash_purchase']);
        $closed = PropertyAcquisition::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'cash_purchase']);
        $closed->forceFill(['status' => 'completed'])->save();

        $openLink = AcquisitionParty::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_acquisition_id' => $open->id, 'party_id' => $party->id, 'role' => 'seller']);
        $closedLink = AcquisitionParty::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_acquisition_id' => $closed->id, 'party_id' => $party->id, 'role' => 'seller']);

        $this->assertDeleteRejected($closedLink);
        $openLink->delete();
        $this->assertFalse(AcquisitionParty::withoutGlobalScopes()->whereKey($openLink->id)->exists());
    }

    public function test_location_with_properties_and_company_identity_party_are_protected(): void
    {
        $company = Company::create(['name' => 'A', 'email' => 'a@test.com']);
        $property = $this->property($company);
        $this->assertDeleteRejected(Location::withoutGlobalScopes()->findOrFail($property->location_id));

        $identity = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'company', 'name' => 'A']);
        $company->forceFill(['self_party_id' => $identity->id])->save();
        $this->assertDeleteRejected($identity);
    }
}
