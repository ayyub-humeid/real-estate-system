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

    private function actor(Company $company): User { $user = User::factory()->create(['company_id' => $company->id]); foreach (['view_any_unit::reservation', 'view_unit::reservation', 'create_unit::reservation', 'update_unit::reservation', 'send_reservation_for_approval', 'approve_and_activate_reservation', 'cancel_unit_reservation'] as $permission) $user->givePermissionTo(Permission::findOrCreate($permission, 'web')); return $user; }
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
        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertActionHidden('send')
            ->assertActionVisible('approve')
            ->callAction('approve')
            ->assertHasNoErrors()
            ->assertActionHidden('approve')
            ->assertActionVisible('cancel');

        $reservation->refresh();
        $this->assertSame('active', $reservation->status->value);
        $this->assertNotNull($reservation->snapshot);
    }

    /** The snapshot keeps the unit's features and the agreed terms as JSON, which the page must read back as words. */
    public function test_the_approved_reservation_page_reads_back_a_snapshot_that_holds_features_and_extra_terms(): void
    {
        $company = Company::create(['name' => 'Reservation Snapshot', 'email' => 'reservation-snapshot@example.test']);
        $actor = $this->actor($company); $unit = $this->unit($company);
        $unit->features()->createMany([
            ['feature_key' => 'balcony', 'name' => 'Balcony', 'value' => 'Sea view'],
            ['feature_key' => 'parking', 'name' => 'Parking', 'value' => '2 covered spaces'],
        ]);
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Customer', 'is_active' => true]);
        $service = app(\App\Services\ReservationWorkflowService::class);
        $reservation = $service->createDraft($actor, ['source' => 'direct', 'unit_id' => $unit->id, 'customer_party_id' => $party->id,
            'transaction_type' => 'sale', 'agreed_amount' => 100000, 'currency' => 'USD',
            'terms_json' => ['handover' => 'Q3', 'fees' => ['registration' => 1500]]]);
        $service->sendForApproval($actor, $reservation);
        $this->actingAs($actor);

        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('approve')
            ->assertHasNoErrors()
            ->assertOk()
            ->assertSee('Balcony: Sea view')
            ->assertSee('registration: 1500');

        $this->assertNotNull($reservation->fresh()->snapshot);
    }

    /** A reservation only holds its unit once approved, so before that the page must say when the unit is gone. */
    public function test_a_reservation_waiting_for_approval_says_when_its_unit_was_taken_by_someone_else(): void
    {
        $company = Company::create(['name' => 'Reservation Clash', 'email' => 'reservation-clash@example.test']);
        $actor = $this->actor($company); $unit = $this->unit($company);
        $party = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Customer', 'is_active' => true]);
        $service = app(\App\Services\ReservationWorkflowService::class);
        $reservation = $service->createDraft($actor, ['source' => 'direct', 'unit_id' => $unit->id, 'customer_party_id' => $party->id,
            'transaction_type' => 'sale', 'agreed_amount' => 100000, 'currency' => 'USD']);
        $service->sendForApproval($actor, $reservation);
        $this->actingAs($actor);

        $warning = __('reservations.guidance.unit_taken', ['unit' => $unit->unit_number, 'status' => status_label('sold')]);
        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])->assertDontSee($warning);

        $unit->forceFill(['status' => 'sold'])->save();

        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])->assertSee($warning);
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
