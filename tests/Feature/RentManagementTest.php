<?php

namespace Tests\Feature;

use App\Enums\{ContractStatus, ContractType, ContractVersionStatus, OpportunityType, RentFrequency, ReservationSource, ReservationStatus};
use App\Filament\Pages\RentRoll;
use App\Filament\Resources\CompanySettingResource\Pages\EditCompanySetting;
use App\Filament\Resources\ContractResource\Pages\CreateContract;
use App\Filament\Resources\UnitReservationResource\Pages\ViewUnitReservation;
use App\Models\{Company, CompanySetting, Contract, ContractParty, ContractPaymentSchedule, ContractTemplate, ContractTemplateVersion, ContractVersion, Location, Party, Property, Unit, UnitReservation, User};
use App\Services\{ContractDraftService, ContractFinancialService, ContractPdfService, RentRollQuery};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Rent is agreed per period but a contract is signed for a total, so the total
 * has to be the rent across the whole term. These tests cover that figure, the
 * rent roll that reads it back, and the contract PDF that has to state how the
 * agreed amount is paid.
 */
class RentManagementTest extends TestCase
{
    use RefreshDatabase;

    // ── The figure a rental contract is signed for ──────────────────────────

    public function test_a_rental_contract_is_worth_the_rent_across_its_whole_term(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Rent total');
        $this->grant($user, ['create_contract', 'create_contract_from_reservation', 'view_any_unit::reservation', 'view_unit::reservation']);
        $tenant = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Term Tenant', 'is_active' => true]);
        $reservation = $this->rentReservation($company, $unit, $tenant, 500, RentFrequency::Monthly, 12);
        $template = $this->templateVersion($user, ContractType::Lease);
        $this->actingAs($user);

        // The button an admin actually clicks on the reservation.
        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('create_contract', [
                'template_version_id' => $template->id,
                'start_date' => '2026-02-01',
                'end_date' => '2027-01-31',
            ])
            ->assertHasNoActionErrors();

        $version = Contract::withoutGlobalScopes()->where('reservation_id', $reservation->id)->firstOrFail()
            ->versions()->firstOrFail();

        // 12 monthly payments of 500, not the 500 the reservation agreed per month.
        $this->assertEquals(6000, $version->total_amount);
        $this->assertEquals(500, $version->rent_amount);
        $this->assertSame(RentFrequency::Monthly->value, $version->rent_frequency);
        $this->assertSame(12, $version->rentTerms()['periods']);
    }

    public function test_a_term_that_is_not_whole_payment_periods_is_refused_with_an_actionable_message(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Odd term');
        $this->grant($user, ['create_contract', 'create_contract_from_reservation', 'view_any_unit::reservation', 'view_unit::reservation']);
        $tenant = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Odd Tenant', 'is_active' => true]);
        $reservation = $this->rentReservation($company, $unit, $tenant, 1500, RentFrequency::Quarterly, 12);
        $template = $this->templateVersion($user, ContractType::Lease);

        // Five months cannot be paid in equal quarterly instalments.
        try {
            app(ContractDraftService::class)->createFromReservation($user, $reservation, [
                'template_version_id' => $template->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-05-31',
            ]);
            $this->fail('A term that is not whole quarters was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('end_date', $exception->errors());
        }

        $this->actingAs($user);

        // The admin is told, rather than left with a silently wrong total.
        Livewire::test(ViewUnitReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('create_contract', [
                'template_version_id' => $template->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-05-31',
            ])
            ->assertNotified(__('crm.errors.action_failed'));

        $this->assertSame(0, Contract::withoutGlobalScopes()->where('reservation_id', $reservation->id)->count());
    }

    public function test_a_sale_total_is_the_agreed_price_and_carries_no_rent_terms(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Sale untouched');
        $this->grant($user, ['create_contract', 'create_contract_from_reservation']);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Price Buyer', 'is_active' => true]);
        $reservation = UnitReservation::create([
            'company_id' => $company->id, 'reservation_number' => 'RES-'.str()->random(6),
            'source' => ReservationSource::Direct, 'transaction_type' => OpportunityType::Sale,
            'unit_id' => $unit->id, 'customer_party_id' => $buyer->id,
            'agreed_amount' => 150000, 'currency' => 'USD', 'valid_until' => now()->addWeek(),
        ]);
        $reservation->forceFill(['status' => ReservationStatus::Active])->save();

        $contract = app(ContractDraftService::class)->createFromReservation($user, $reservation->fresh(), [
            'template_version_id' => $this->templateVersion($user, ContractType::Sale)->id,
            'start_date' => '2026-02-01',
        ]);
        $version = $contract->versions()->firstOrFail();

        $this->assertEquals(150000, $version->total_amount);
        $this->assertNull($version->rent_amount);
        $this->assertNull($version->rent_frequency);
    }

    public function test_the_contract_form_asks_for_the_rent_and_derives_the_total(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Rent form');
        $this->grant($user, ['create_contract', 'view_any_contract', 'view_contract']);
        $tenant = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Form Tenant', 'is_active' => true]);
        $template = $this->templateVersion($user, ContractType::Lease);
        $this->actingAs($user);

        // The form shows the derived total as soon as the rent is typed in.
        Livewire::test(CreateContract::class)
            ->assertFormFieldExists('rent_amount')
            ->assertFormFieldExists('rent_frequency')
            ->fillForm([
                'contract_type' => ContractType::Lease->value,
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'template_version_id' => $template->id,
                'currency' => 'USD',
                'rent_frequency' => RentFrequency::Monthly->value,
                'start_date' => '2026-03-01',
                'end_date' => '2027-02-28',
            ])
            ->set('data.rent_amount', 800)
            ->assertFormSet(['total_amount' => '9600.00']);

        $contract = app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Lease->value,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'template_version_id' => $template->id,
            'currency' => 'USD',
            'rent_amount' => 800,
            'rent_frequency' => RentFrequency::Monthly->value,
            'start_date' => '2026-03-01',
            'end_date' => '2027-02-28',
            'parties' => [['party_id' => $tenant->id, 'role' => 'tenant']],
        ]);
        $version = $contract->versions()->firstOrFail();

        $this->assertEquals(9600, $version->total_amount);
        $this->assertEquals(800, $version->rent_amount);
    }

    public function test_editing_a_draft_recalculates_the_total_from_the_new_rent(): void
    {
        [$company, $user] = $this->activeLease(1500, 500);
        $version = ContractVersion::withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
        $version->forceFill(['status' => ContractVersionStatus::Draft])->save();
        $this->grant($user, ['update_contract_version']);

        $updated = app(ContractDraftService::class)->updateDraft($user, $version->fresh(), [
            'currency' => 'USD',
            'rent_amount' => 600,
            'rent_frequency' => RentFrequency::Monthly->value,
            'start_date' => $version->start_date->toDateString(),
            'end_date' => $version->end_date->toDateString(),
            'parties' => [],
        ]);

        $this->assertEquals(1800, $updated->total_amount);
    }

    // ── The rent roll ───────────────────────────────────────────────────────

    public function test_the_rent_roll_figures_match_the_contract_its_rows_come_from(): void
    {
        [$company, $user, , $contract] = $this->activeLease(1500, 500);
        $this->grant($user, ['page_RentRoll']);
        $this->actingAs($user);

        $summary = app(ContractFinancialService::class)->summary($contract);
        $row = (new RentRollQuery($user))->builder()->firstOrFail();

        // 500 overdue less the 200 receipt, and the two future payments still open.
        $this->assertEquals(300, (float) $row->overdue_amount);
        $this->assertEquals((float) $summary['overdue'], (float) $row->overdue_amount);
        $this->assertEquals((float) $summary['outstanding'], (float) $row->outstanding_amount);
        $this->assertSame(today()->addDays(5)->toDateString(), substr((string) $row->next_due_date, 0, 10));
        $this->assertSame('Roll Tenant', $row->tenant_name);
        $this->assertEquals(500, (float) $row->rent_amount);

        Livewire::test(RentRoll::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$contract])
            ->assertSee('Roll Tenant')
            ->assertSee('300.00');
    }

    public function test_the_company_grace_period_decides_what_counts_as_late(): void
    {
        [$company, $user, , $contract] = $this->activeLease(1500, 500);
        CompanySetting::create(['company_id' => $company->id, 'payment_grace_period_days' => 90]);
        $this->grant($user, ['page_RentRoll']);
        $this->actingAs($user);

        $query = new RentRollQuery($user);

        $this->assertSame(90, $query->graceDays());
        $this->assertSame(today()->subDays(90)->toDateString(), $query->overdueBefore()->toDateString());
        // The payment is two months late, which this company still allows.
        $this->assertEquals(0, (float) $query->builder()->firstOrFail()->overdue_amount);
        $this->assertEquals(1300, (float) $query->builder()->firstOrFail()->outstanding_amount);

        Livewire::test(RentRoll::class)
            ->assertSuccessful()
            ->filterTable('overdue')
            ->assertCanNotSeeTableRecords([$contract]);
    }

    public function test_each_rent_roll_filter_narrows_the_list_as_labelled(): void
    {
        [$company, $user, , $overdue] = $this->activeLease(1500, 500);
        $this->grant($user, ['page_RentRoll']);
        $this->actingAs($user);

        // A second rental of the same company with nothing owed on it at all.
        $settled = $this->leaseWithoutSchedule($company, $user, 'Settled Tenant');

        Livewire::test(RentRoll::class)
            ->assertCanSeeTableRecords([$overdue, $settled])
            ->filterTable('overdue')
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$settled])
            ->resetTableFilters()
            ->filterTable('no_schedule')
            ->assertCanSeeTableRecords([$settled])
            ->assertCanNotSeeTableRecords([$overdue])
            ->resetTableFilters()
            ->filterTable('due_within', '7')
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$settled])
            ->resetTableFilters()
            ->filterTable('ending_soon')
            ->assertCanSeeTableRecords([$overdue])
            ->resetTableFilters()
            ->filterTable('property_id', $overdue->property_id)
            ->assertCanSeeTableRecords([$overdue]);
    }

    public function test_a_company_never_sees_another_companys_rent_roll(): void
    {
        [, , , $theirContract] = $this->activeLease(1500, 500);
        [, $outsider] = $this->companyUserAndUnit('Outsider');
        $this->grant($outsider, ['page_RentRoll']);
        $this->actingAs($outsider);

        $this->assertSame(0, (new RentRollQuery($outsider))->builder()->count());

        Livewire::test(RentRoll::class)
            ->assertSuccessful()
            ->assertCanNotSeeTableRecords([$theirContract]);
    }

    public function test_the_rent_roll_needs_its_own_permission(): void
    {
        [, $user] = $this->companyUserAndUnit('Rent roll permission');
        $this->grant($user, ['view_any_contract']);
        $this->actingAs($user);

        $this->assertFalse(RentRoll::canAccess());

        $this->grant($user, ['page_RentRoll']);
        $user->forgetCachedPermissions();

        $this->assertTrue(RentRoll::canAccess());
    }

    // ── The contract PDF ────────────────────────────────────────────────────

    public function test_the_contract_pdf_states_the_rent_terms_and_how_the_total_is_paid(): void
    {
        [$company, $user, , $contract] = $this->activeLease(1500, 500);
        CompanySetting::create([
            'company_id' => $company->id,
            'company_legal_name' => 'Roll Holdings Ltd',
            'contract_header_note' => 'Branch licence 4417',
        ]);
        $version = $contract->versions()->firstOrFail();
        $version->forceFill(['template_version_id' => $this->templateVersion($user, ContractType::Lease)->id])->save();
        $version = $version->fresh();

        $service = app(ContractPdfService::class);
        $plan = $this->callPrivate($service, 'paymentPlan', $version);
        $rentTerms = $this->callPrivate($service, 'rentTerms', $version);

        $this->assertTrue($plan['issued']);
        $this->assertTrue($plan['reconciles'], 'The issued instalments must add up to the contract total.');
        $this->assertSame(3, $plan['installments']->count());
        $this->assertSame('1500.00', $plan['total']);
        $this->assertSame(3, $rentTerms['periods']);

        // Render the real template, so a mistake in the new sections is caught here.
        $html = view('pdf.contract-version', [
            'contractVersion' => $version->load(['contract', 'parties.party', 'terms']),
            'contract' => $version->contract,
            'settings' => CompanySetting::withoutGlobalScopes()->where('company_id', $company->id)->first(),
            'body' => '<p>Lease body</p>',
            'contractSummary' => ['unit' => 'A-101', 'property' => 'Roll Property', 'project' => '—'],
            'draft' => false,
            'paymentPlan' => $plan,
            'rentTerms' => $rentTerms,
            'signatures' => [],
            'images' => [],
        ])->render();

        $this->assertStringContainsString('Branch licence 4417', $html);
        $this->assertStringContainsString(__('contract_domain.pdf.payment_distribution'), $html);
        $this->assertStringContainsString('500.00 USD', $html);
        $this->assertStringContainsString(__('contract_domain.pdf.payment_total'), $html);
        $this->assertStringContainsString(__('contract_domain.rent.frequency'), $html);
        $this->assertStringNotContainsString(__('contract_domain.pdf.schedule_mismatch'), $html);
        $this->assertStringNotContainsString(__('contract_domain.pdf.schedule_missing'), $html);
    }

    public function test_the_pdf_says_so_when_no_schedule_has_been_prepared(): void
    {
        [$company, $user, , $contract] = $this->activeLease(1500, 500, withSchedule: false);
        $version = $contract->versions()->firstOrFail();

        $plan = $this->callPrivate(app(ContractPdfService::class), 'paymentPlan', $version);
        $this->assertNull($plan);

        $html = view('pdf.contract-version', [
            'contractVersion' => $version->load(['contract', 'parties.party', 'terms']),
            'contract' => $version->contract,
            'settings' => null,
            'body' => '<p>Lease body</p>',
            'contractSummary' => ['unit' => 'A-101', 'property' => 'Roll Property', 'project' => '—'],
            'draft' => true,
            'paymentPlan' => null,
            'rentTerms' => $this->callPrivate(app(ContractPdfService::class), 'rentTerms', $version),
            'signatures' => [],
            'images' => [],
        ])->render();

        $this->assertStringContainsString(__('contract_domain.pdf.schedule_missing'), $html);
    }

    public function test_the_official_pdf_still_renders_with_the_new_sections(): void
    {
        [$company, $user, , $contract] = $this->activeLease(1500, 500);
        $this->grant($user, ['view_contract', 'generate_contract_pdf']);
        CompanySetting::create(['company_id' => $company->id, 'contract_header_note' => 'Licence 1', 'show_contract_stamp' => false]);
        $version = $contract->versions()->firstOrFail();
        $version->forceFill([
            'template_version_id' => $this->templateVersion($user, ContractType::Lease)->id,
            'status' => ContractVersionStatus::Approved,
        ])->save();

        $pdf = app(ContractPdfService::class)->generateOfficial($user, $version->fresh());

        $this->assertSame('official_approved_pdf', $pdf->document->document_type->value);
    }

    public function test_a_company_can_set_its_own_extra_contract_header_line(): void
    {
        [$company, $user] = $this->companyUserAndUnit('Header note');
        $this->grant($user, ['view_any_company::setting', 'view_company::setting', 'update_company::setting']);
        $settings = CompanySetting::create(['company_id' => $company->id]);
        $this->actingAs($user);

        Livewire::test(EditCompanySetting::class, ['record' => $settings->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('contract_header_note')
            ->fillForm(['contract_header_note' => 'Ramallah branch · Licence 9921'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Ramallah branch · Licence 9921', $settings->fresh()->contract_header_note);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * An active monthly rental with an issued three-payment schedule: the first
     * payment is 30 days late and partly paid, the next falls due in five days.
     *
     * @return array{0: Company, 1: User, 2: Unit, 3: Contract}
     */
    private function activeLease(int $total, int $rent, bool $withSchedule = true): array
    {
        [$company, $user, $unit] = $this->companyUserAndUnit('Roll');
        $this->grant($user, [
            'create_contract_payment_schedule', 'submit_contract_payment_schedule',
            'approve_contract_payment_schedule', 'record_contract_payment',
            'allocate_contract_payment', 'view_contract_financials',
        ]);
        // Two months in, so the first payment is late and the term ends soon.
        $start = today()->subMonths(2);

        $contract = Contract::create([
            'company_id' => $company->id, 'contract_number' => 'CON-'.str()->random(8),
            'contract_type' => ContractType::Lease, 'status' => ContractStatus::Active,
            'unit_id' => $unit->id, 'property_id' => $unit->property_id, 'created_by' => $user->id,
        ]);
        $version = ContractVersion::create([
            'company_id' => $company->id, 'contract_id' => $contract->id, 'version_number' => 1,
            'status' => ContractVersionStatus::Signed, 'total_amount' => $total, 'currency' => 'USD',
            'rent_amount' => $rent, 'rent_frequency' => RentFrequency::Monthly->value,
            'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addMonths(3)->subDay()->toDateString(),
            'created_by' => $user->id,
        ]);
        $tenant = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Roll Tenant', 'is_active' => true]);
        ContractParty::create([
            'company_id' => $company->id, 'contract_version_id' => $version->id,
            'party_id' => $tenant->id, 'role' => 'tenant', 'snapshot' => ['name' => $tenant->name],
        ]);

        if ($withSchedule) {
            $service = app(ContractFinancialService::class);
            $schedule = $service->createDraft($user, $contract, $version, [
                'installments' => [
                    ['amount' => $rent, 'due_date' => $start->toDateString()],
                    ['amount' => $rent, 'due_date' => today()->addDays(5)->toDateString()],
                    ['amount' => $rent, 'due_date' => today()->addDays(35)->toDateString()],
                ],
            ]);
            $service->submit($user, $schedule);
            $schedule = $service->issue($user, $schedule->fresh());
            $first = $schedule->installments()->orderBy('sequence')->firstOrFail();

            $service->recordReceipt($user, $schedule, [
                'party_id' => $tenant->id, 'amount' => 200, 'currency' => 'USD',
                'payment_date' => today()->toDateString(), 'payment_method' => 'cash',
                'idempotency_key' => 'rent-'.$contract->id,
                'allocations' => [['installment_id' => $first->id, 'allocated_amount' => 200, 'currency' => 'USD']],
            ]);
        }

        return [$company, $user, $unit, $contract->fresh()];
    }

    /** A second active rental with no payment schedule yet, ending far in the future. */
    private function leaseWithoutSchedule(Company $company, User $user, string $tenantName): Contract
    {
        $unit = Unit::create([
            'company_id' => $company->id, 'property_id' => Property::withoutGlobalScopes()->where('company_id', $company->id)->value('id'),
            'unit_number' => 'B-'.str()->random(4), 'status' => 'ready', 'type' => 'apartment',
        ]);
        $contract = Contract::create([
            'company_id' => $company->id, 'contract_number' => 'CON-'.str()->random(8),
            'contract_type' => ContractType::Lease, 'status' => ContractStatus::Active,
            'unit_id' => $unit->id, 'property_id' => $unit->property_id, 'created_by' => $user->id,
        ]);
        $version = ContractVersion::create([
            'company_id' => $company->id, 'contract_id' => $contract->id, 'version_number' => 1,
            'status' => ContractVersionStatus::Signed, 'total_amount' => 12000, 'currency' => 'USD',
            'rent_amount' => 1000, 'rent_frequency' => RentFrequency::Monthly->value,
            'start_date' => today()->toDateString(), 'end_date' => today()->addMonths(12)->subDay()->toDateString(),
            'created_by' => $user->id,
        ]);
        $party = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => $tenantName, 'is_active' => true]);
        ContractParty::create([
            'company_id' => $company->id, 'contract_version_id' => $version->id,
            'party_id' => $party->id, 'role' => 'tenant', 'snapshot' => ['name' => $party->name],
        ]);

        return $contract->fresh();
    }

    private function rentReservation(Company $company, Unit $unit, Party $customer, int $rent, RentFrequency $frequency, int $termMonths): UnitReservation
    {
        $reservation = UnitReservation::create([
            'company_id' => $company->id, 'reservation_number' => 'RES-'.str()->random(6),
            'source' => ReservationSource::Direct, 'transaction_type' => OpportunityType::Rent,
            'unit_id' => $unit->id, 'customer_party_id' => $customer->id,
            'agreed_amount' => $rent, 'currency' => 'USD',
            'rent_frequency' => $frequency->value, 'lease_term_value' => $termMonths, 'lease_term_unit' => 'month',
            'valid_until' => now()->addWeek(),
        ]);
        $reservation->forceFill(['status' => ReservationStatus::Active])->save();

        return $reservation->fresh();
    }

    /** @return array{0: Company, 1: User, 2: Unit} */
    private function companyUserAndUnit(string $prefix): array
    {
        $company = Company::create(['name' => $prefix.' Co '.str()->random(4), 'email' => str()->uuid().'@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::create(['company_id' => $company->id, 'name' => $prefix.' City', 'type' => 'city']);
        $property = Property::create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => $prefix.' Property', 'address' => 'Test address']);
        $unit = Unit::create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-101', 'status' => 'ready', 'type' => 'apartment']);

        return [$company, $user, $unit];
    }

    private function templateVersion(User $user, ContractType $type): ContractTemplateVersion
    {
        $template = ContractTemplate::create([
            'company_id' => $user->company_id, 'name' => 'Tpl '.str()->uuid(),
            'contract_type' => $type, 'is_active' => true,
        ]);

        return ContractTemplateVersion::create([
            'company_id' => $user->company_id, 'contract_template_id' => $template->id, 'version_number' => 1,
            'body' => '<p>{{contract_number}}</p>', 'clauses' => [['type' => 'legal', 'title' => 'Term', 'text' => 'Legal term.']],
            'created_by' => $user->id,
        ]);
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);
        $user->forgetCachedPermissions();
    }

    private function callPrivate(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod($object, $method))->invoke($object, ...$arguments);
    }
}
