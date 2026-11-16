<?php

namespace Tests\Feature;

use App\Enums\{ReservationStatus, UnitStatus};
use App\Filament\Resources\UnitReservationResource\Pages\{CreateUnitReservation, ViewUnitReservation};
use App\Models\{Company, Location, Party, Property, Unit, UnitReservation, User};
use App\Services\ReservationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnitReservationStatusTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach (['create_unit::reservation', 'view_any_unit::reservation', 'view_unit::reservation', 'update_unit::reservation', 'send_reservation_for_approval', 'approve_and_activate_reservation', 'cancel_unit_reservation', 'expire_unit_reservation'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }

    private function unit(Company $company, string $status = UnitStatus::Ready->value): Unit
    {
        $location = \App\Models\Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'City', 'type' => 'city']);
        $property = \App\Models\Property::withoutGlobalScopes()->create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Property', 'address' => 'Address']);
        return Unit::withoutGlobalScopes()->create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'U-101', 'type' => 'apartment', 'status' => $status]);
    }

    private function party(Company $company): Party
    {
        return Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'individual', 'name' => 'Customer', 'phone' => '0591234567', 'is_active' => true]);
    }

    private function createAndActivateDraft(Company $company, User $actor, Unit $unit, Party $party): UnitReservation
    {
        $this->actingAs($actor);

        Livewire::test(CreateUnitReservation::class)
            ->assertFormFieldExists('source')->assertFormFieldExists('customer_party_id')->assertFormFieldExists('unit_id')->assertFormFieldExists('agreed_amount')
            ->fillForm([
                'source' => 'direct',
                'customer_party_id' => $party->id,
                'unit_id' => $unit->id,
                'transaction_type' => 'sale',
                'agreed_amount' => 100000,
                'currency' => 'USD',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $reservation = UnitReservation::withoutGlobalScopes()->firstOrFail();
        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('send')
            ->assertHasNoTableActionErrors();
        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('approve')
            ->assertHasNoTableActionErrors();

        return $reservation->refresh();
    }

    public function test_activating_reservation_sets_unit_to_reserved_with_status_history(): void
    {
        $company = Company::create(['name' => 'UI', 'email' => 'ui@example.test']);
        $actor = $this->actor($company);
        $unit = $this->unit($company);
        $party = $this->party($company);

        $this->assertSame(UnitStatus::Ready->value, $unit->fresh()->status);

        $this->createAndActivateDraft($company, $actor, $unit, $party);

        $this->assertSame(UnitStatus::Reserved->value, $unit->fresh()->status);
        $this->assertNotNull($unit->fresh()->statusHistories()->where('to_status', UnitStatus::Reserved->value)->first());
    }

    public function test_cancelling_active_reservation_releases_unit_to_ready(): void
    {
        $company = Company::create(['name' => 'UI', 'email' => 'ui@example.test']);
        $actor = $this->actor($company);
        $unit = $this->unit($company);
        $party = $this->party($company);

        $reservation = $this->createAndActivateDraft($company, $actor, $unit, $party);

        $this->assertSame(UnitStatus::Reserved->value, $unit->fresh()->status);

        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('cancel', ['reason' => 'Customer changed mind'])
            ->assertHasNoTableActionErrors();

        $reservation->refresh();
        $this->assertSame('cancelled', $reservation->status->value);
        $this->assertSame(UnitStatus::Ready->value, $unit->fresh()->status);
        $this->assertNotNull($unit->fresh()->statusHistories()->where('to_status', UnitStatus::Ready->value)->first());
    }

    public function test_expiring_active_reservation_releases_unit_to_ready(): void
    {
        $company = Company::create(['name' => 'UI', 'email' => 'ui@example.test']);
        $actor = $this->actor($company);
        $unit = $this->unit($company);
        $party = $this->party($company);

        $reservation = $this->createAndActivateDraft($company, $actor, $unit, $party);

        $this->assertSame(UnitStatus::Reserved->value, $unit->fresh()->status);

        $reservation->update(['valid_until' => now()->subDay(), 'status' => ReservationStatus::Active->value, 'activated_at' => now()->subDay()]);
        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('expire')
            ->assertHasNoTableActionErrors();

        $reservation->refresh();
        $this->assertSame('expired', $reservation->status->value);
        $this->assertSame(UnitStatus::Ready->value, $unit->fresh()->status);
    }

    public function test_double_booking_prevention_on_active_unit(): void
    {
        $company = Company::create(['name' => 'UI', 'email' => 'ui@example.test']);
        $actor = $this->actor($company);
        $unit = $this->unit($company);
        $party = $this->party($company);
        $otherParty = $this->party($company);

        $this->actingAs($actor);

        $reservation1 = $this->createAndActivateDraft($company, $actor, $unit, $party);
        $this->assertSame(UnitStatus::Reserved->value, $unit->fresh()->status);

        // Try to approve a second active reservation on the same unit directly via service
        $reservation2 = UnitReservation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'reservation_number' => 'RES-TEST-000002',
            'source' => \App\Enums\ReservationSource::Direct->value,
            'transaction_type' => 'sale',
            'unit_id' => $unit->id,
            'customer_party_id' => $otherParty->id,
            'agreed_amount' => 120000,
            'currency' => 'USD',
        ]);
        // 'status' is not fillable — set it explicitly to Pending like sendForApproval does
        $reservation2->forceFill(['status' => ReservationStatus::Pending->value, 'submitted_at' => now()])->save();

        $service = app(ReservationWorkflowService::class);
        try {
            $service->approveAndActivate($actor, $reservation2->fresh());
            $this->fail('Expected authorization/validation exception for double booking');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rejected on the unit_id field — either by the status guard
            // (unit_not_reservable) or the availability guard (unit_already_reserved).
            $this->assertArrayHasKey('unit_id', $e->errors());
        }

        // Unit remains reserved (not double-booked)
        $this->assertSame(UnitStatus::Reserved->value, $unit->fresh()->status);
    }

    public function test_unit_status_transitions(): void
    {
        $this->assertTrue(UnitStatus::Reserved->canTransitionTo(UnitStatus::Ready));
        $this->assertFalse(UnitStatus::Draft->canTransitionTo(UnitStatus::Reserved));
    }
}
