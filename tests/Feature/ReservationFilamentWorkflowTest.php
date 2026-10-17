<?php

namespace Tests\Feature;

use App\Filament\Resources\UnitReservationResource\Pages\{CreateUnitReservation, ListUnitReservations, ViewUnitReservation};
use App\Models\{Company, Location, Party, Property, Unit, UnitReservation, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservationFilamentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company): User { $user = User::factory()->create(['company_id' => $company->id]); foreach (['view_any_unit::reservation', 'view_unit::reservation', 'create_unit::reservation', 'update_unit::reservation', 'send_reservation_for_approval', 'approve_and_activate_reservation'] as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web')); return $user; }
    private function unit(Company $company): Unit { $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City', 'type' => 'city']); $property = Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Property', 'address' => 'Address']); return Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'U-101', 'type' => 'apartment', 'status' => 'ready']); }

    public function test_direct_reservation_form_and_view_workflow_actions_are_available_in_filament(): void
    {
        $company = Company::create(['name' => 'Reservation UI', 'email' => 'reservation-ui@example.test']); $actor = $this->actor($company); $unit = $this->unit($company); $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Customer', 'is_active' => true]); $this->actingAs($actor);
        Livewire::test(CreateUnitReservation::class)
            ->assertFormFieldExists('source')->assertFormFieldExists('customer_party_id')->assertFormFieldExists('unit_id')->assertFormFieldExists('agreed_amount')
            ->fillForm(['source' => 'direct', 'customer_party_id' => $party->id, 'unit_id' => $unit->id, 'transaction_type' => 'sale', 'agreed_amount' => 100000, 'currency' => 'USD'])
            ->call('create')->assertHasNoFormErrors();
        $reservation = UnitReservation::withoutGlobalScopes()->firstOrFail();
        Livewire::test(ListUnitReservations::class)->assertCanSeeTableRecords([$reservation])->callTableAction('send', $reservation)->assertHasNoTableActionErrors();
        $reservation->refresh(); $this->assertSame('pending', $reservation->status->value);
        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])->assertActionVisible('approve');
    }

    public function test_create_failure_returns_a_clear_inline_validation_error_instead_of_silently_not_saving(): void
    {
        $company = Company::create(['name' => 'Reservation UI A', 'email' => 'reservation-ui-a@example.test']);
        $other = Company::create(['name' => 'Reservation UI B', 'email' => 'reservation-ui-b@example.test']);
        $actor = $this->actor($company); $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Customer', 'is_active' => true]);
        $this->actingAs($actor);

        Livewire::test(CreateUnitReservation::class)
            ->fillForm(['source' => 'direct', 'customer_party_id' => $party->id, 'unit_id' => $this->unit($other)->id, 'transaction_type' => 'sale', 'agreed_amount' => 100000, 'currency' => 'USD'])
            ->call('create')
            ->assertHasErrors(['unit_id']);
    }
}
