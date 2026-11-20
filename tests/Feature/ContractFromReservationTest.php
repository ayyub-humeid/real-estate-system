<?php

namespace Tests\Feature;

use App\Enums\{ContractStatus, ContractType, OpportunityType, ReservationSource, ReservationStatus};
use App\Filament\Resources\ContractResource\Pages\CreateContract;
use App\Filament\Resources\UnitReservationResource\Pages\ViewUnitReservation;
use App\Models\{Company, Contract, ContractTemplate, ContractTemplateVersion, Location, Party, Property, Unit, UnitReservation, User};
use App\Services\ContractDraftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Reservation -> Contract in one click: the contract type, unit, amount,
 * currency and both parties (customer + the company's own legal party) come
 * from the reservation, so nothing is re-typed.
 */
class ContractFromReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sale_reservation_creates_a_contract_with_buyer_and_seller(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Sale from reservation');
        $this->grant($user, ['create_contract', 'create_contract_from_reservation']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Sale Buyer', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $buyer, OpportunityType::Sale);
        $template = $this->templateVersion($user, ContractType::Sale);

        $contract = app(ContractDraftService::class)->createFromReservation($user, $reservation, [
            'template_version_id' => $template->id,
            'start_date' => now()->toDateString(),
        ]);

        $this->assertSame(ContractType::Sale, $contract->contract_type);
        $this->assertSame($reservation->id, $contract->reservation_id);
        $this->assertSame($unit->id, $contract->unit_id);
        $version = $contract->versions()->firstOrFail();
        $this->assertEquals(150000, $version->total_amount);
        $this->assertSame('USD', $version->currency);
        $this->assertDatabaseHas('contract_parties', ['party_id' => $buyer->id, 'role' => 'buyer']);
        $companyPartyId = $company->fresh()->self_party_id;
        $this->assertNotNull($companyPartyId);
        $this->assertDatabaseHas('contract_parties', ['party_id' => $companyPartyId, 'role' => 'seller']);
    }

    public function test_a_rent_reservation_creates_a_lease_contract_with_tenant_and_landlord(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Rent from reservation');
        $this->grant($user, ['create_contract', 'create_contract_from_reservation']);
        $tenant = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Rent Tenant', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $tenant, OpportunityType::Rent, ['lease_term_value' => 12, 'lease_term_unit' => 'month']);
        $template = $this->templateVersion($user, ContractType::Lease);

        $contract = app(ContractDraftService::class)->createFromReservation($user, $reservation, [
            'template_version_id' => $template->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertSame(ContractType::Lease, $contract->contract_type);
        $this->assertDatabaseHas('contract_parties', ['party_id' => $tenant->id, 'role' => 'tenant']);
        $companyPartyId = $company->fresh()->self_party_id;
        $this->assertDatabaseHas('contract_parties', ['party_id' => $companyPartyId, 'role' => 'landlord']);
    }

    public function test_only_an_active_reservation_can_be_used(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Draft reservation');
        $this->grant($user, ['create_contract', 'create_contract_from_reservation']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Draft Buyer', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $buyer, OpportunityType::Sale, [], ReservationStatus::Pending);

        try {
            app(ContractDraftService::class)->createFromReservation($user, $reservation);
            $this->fail('A non-active reservation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation_id', $exception->errors());
        }
    }

    public function test_a_second_contract_for_the_same_reservation_is_refused(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Duplicate contract');
        $this->grant($user, ['create_contract', 'create_contract_from_reservation']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Duplicate Buyer', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $buyer, OpportunityType::Sale);
        app(ContractDraftService::class)->createFromReservation($user, $reservation);

        try {
            app(ContractDraftService::class)->createFromReservation($user, $reservation);
            $this->fail('A second contract for the same reservation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation_id', $exception->errors());
        }
    }

    public function test_the_custom_permission_is_required_even_with_the_generic_create_permission(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Missing custom permission');
        $this->grant($user, ['create_contract']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $buyer, OpportunityType::Sale);

        $this->expectException(AuthorizationException::class);
        app(ContractDraftService::class)->createFromReservation($user, $reservation);
    }

    public function test_the_generic_create_contract_permission_is_still_required(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Missing generic permission');
        $this->grant($user, ['create_contract_from_reservation']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $buyer, OpportunityType::Sale);

        $this->expectException(AuthorizationException::class);
        app(ContractDraftService::class)->createFromReservation($user, $reservation);
    }

    public function test_another_companys_reservation_is_refused(): void
    {
        [$company, , $unit] = $this->companyUserAndUnit('Owner company');
        [, $foreignUser] = $this->companyUserAndUnit('Foreign company');
        $this->grant($foreignUser, ['create_contract', 'create_contract_from_reservation']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Owner Buyer', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $buyer, OpportunityType::Sale);

        $this->expectException(AuthorizationException::class);
        app(ContractDraftService::class)->createFromReservation($foreignUser, $reservation);
    }

    public function test_the_create_contract_button_is_hidden_without_permission_or_when_already_converted(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Button visibility');
        $this->grant($user, ['view_any_unit::reservation', 'view_unit::reservation']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Button Buyer', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $buyer, OpportunityType::Sale);
        $this->actingAs($user);

        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertActionHidden('create_contract');

        $this->grant($user, ['create_contract', 'create_contract_from_reservation']);
        $user->forgetCachedPermissions();

        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertActionVisible('create_contract');

        app(ContractDraftService::class)->createFromReservation($user, $reservation);

        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertActionHidden('create_contract');
    }

    public function test_the_create_contract_action_creates_the_contract_and_redirects_to_it(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Create contract action');
        $this->grant($user, ['create_contract', 'create_contract_from_reservation', 'view_any_unit::reservation', 'view_unit::reservation']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Action Buyer', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $buyer, OpportunityType::Sale);
        $template = $this->templateVersion($user, ContractType::Sale);
        $this->actingAs($user);

        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('create_contract', ['template_version_id' => $template->id, 'start_date' => now()->toDateString()])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $contract = Contract::withoutGlobalScopes()->where('reservation_id', $reservation->id)->firstOrFail();
        $this->assertSame(ContractStatus::Draft, $contract->status);
    }

    public function test_choosing_a_reservation_on_the_contract_create_form_fills_the_type_and_both_parties(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Create form prefill');
        $this->grant($user, ['create_contract', 'view_any_contract', 'view_contract']);
        $tenant = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Form Tenant', 'is_active' => true]);
        $reservation = $this->reservation($company, $unit, $tenant, OpportunityType::Rent);
        $this->actingAs($user);

        Livewire::test(CreateContract::class)
            ->fillForm(['reservation_id' => $reservation->id])
            ->assertFormSet(fn (array $state): bool => $state['contract_type'] === ContractType::Lease->value
                && $state['unit_id'] === $unit->id
                && count($state['parties']) === 2
                && collect($state['parties'])->pluck('role')->sort()->values()->all() === ['landlord', 'tenant']
                && collect($state['parties'])->pluck('party_id')->contains($tenant->id));
    }

    private function reservation(Company $company, Unit $unit, Party $customer, OpportunityType $type, array $extra = [], ReservationStatus $status = ReservationStatus::Active): UnitReservation
    {
        $reservation = UnitReservation::create(array_merge([
            'company_id' => $company->id,
            'reservation_number' => 'RES-TEST-'.str()->random(6),
            'source' => ReservationSource::Direct,
            'transaction_type' => $type,
            'unit_id' => $unit->id,
            'customer_party_id' => $customer->id,
            'agreed_amount' => 150000,
            'currency' => 'USD',
            'valid_until' => now()->addWeek(),
        ], $extra));
        $reservation->forceFill(['status' => $status])->save();

        return $reservation->fresh();
    }

    private function companyUserAndUnit(string $prefix): array
    {
        $company = Company::create(['name' => $prefix.' Co', 'email' => str($prefix)->slug().'@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::create(['company_id' => $company->id, 'name' => $prefix.' City', 'type' => 'city']);
        $property = Property::create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => $prefix.' Property', 'address' => 'Test address']);
        $unit = Unit::create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-101', 'rent_price' => 0, 'status' => 'ready', 'type' => 'apartment']);

        return [$company, $user, $unit];
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);
    }

    private function templateVersion(User $user, ContractType $type): ContractTemplateVersion
    {
        $template = ContractTemplate::create([
            'company_id' => $user->company_id,
            'name' => 'Test '.$type->value.' '.str()->uuid(),
            'contract_type' => $type,
            'is_active' => true,
        ]);

        return ContractTemplateVersion::create([
            'company_id' => $user->company_id,
            'contract_template_id' => $template->id,
            'version_number' => 1,
            'body' => '<p>{{contract_number}}</p>',
            'clauses' => [['type' => 'legal', 'title' => 'Template term', 'text' => 'Template legal term.']],
            'created_by' => $user->id,
        ]);
    }
}
