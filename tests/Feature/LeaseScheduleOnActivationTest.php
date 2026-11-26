<?php

namespace Tests\Feature;

use App\Enums\{ContractPaymentScheduleStatus, ContractStatus, ContractType, ContractVersionStatus, RentFrequency};
use App\Filament\Resources\ContractResource\Pages\ViewContract;
use App\Models\{Company, Contract, ContractTemplate, ContractTemplateVersion, ContractVersion, Document, DocumentVersion, Location, Party, Property, Unit, User};
use App\Services\{ContractDraftService, ContractFinancialService, ContractWorkflowService, RentRollQuery};
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Activating a lease issues its rent schedule straight from the approved terms,
 * so rent can be tracked without anyone rebuilding the instalments by hand.
 */
class LeaseScheduleOnActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_a_lease_issues_its_rent_schedule_from_the_terms(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit();
        $start = today();
        $contract = $this->activateLease($user, $unit, rent: 500, frequency: RentFrequency::Monthly, termMonths: 12, start: $start);

        $schedule = $contract->paymentSchedules()->firstOrFail();
        $this->assertSame(ContractPaymentScheduleStatus::Issued, $schedule->status);
        $this->assertSame('6000.0000', $schedule->total_amount);

        $installments = $schedule->installments()->orderBy('sequence')->get();
        $this->assertCount(12, $installments);
        $this->assertTrue($installments->every(fn ($line): bool => (float) $line->amount === 500.0));
        $this->assertSame($start->toDateString(), $installments->first()->due_date->toDateString());
        $this->assertSame($start->copy()->addMonthsNoOverflow(11)->toDateString(), $installments->last()->due_date->toDateString());

        // The rent roll and the single-contract authority must now agree.
        $summary = app(ContractFinancialService::class)->summary($contract);
        $this->assertSame('6000.0000', $summary['scheduled']);
        $this->assertSame('6000.0000', $summary['outstanding']);

        $row = (new RentRollQuery($user))->builder()->firstOrFail();
        $this->assertSame(12, (int) $row->installment_count);
        $this->assertEqualsWithDelta(6000, (float) $row->outstanding_amount, 0.001);
    }

    public function test_a_quarterly_lease_gets_one_payment_every_three_months(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit();
        $start = today();
        $contract = $this->activateLease($user, $unit, rent: 900, frequency: RentFrequency::Quarterly, termMonths: 12, start: $start);

        $installments = $contract->paymentSchedules()->firstOrFail()->installments()->orderBy('sequence')->get();
        $this->assertCount(4, $installments);
        $this->assertSame($start->copy()->addMonthsNoOverflow(3)->toDateString(), $installments[1]->due_date->toDateString());
        $this->assertSame('3600.0000', $contract->paymentSchedules()->firstOrFail()->total_amount);
    }

    public function test_a_schedule_prepared_by_hand_is_not_duplicated_at_activation(): void
    {
        [$company, $user, $unit] = $this->companyUserAndUnit();
        $contract = $this->leaseDraft($user, $unit, rent: 500, frequency: RentFrequency::Monthly, termMonths: 12, start: today());
        $version = $contract->versions()->firstOrFail();

        // A person builds their own schedule before the contract is activated.
        $finance = app(ContractFinancialService::class);
        $version->forceFill(['status' => ContractVersionStatus::Signed->value])->save();
        $contract->forceFill(['status' => ContractStatus::Active->value])->save();
        $manual = $finance->createDraft($user, $contract, $version->fresh(), [
            'installments' => [['amount' => 6000, 'due_date' => today()->toDateString()]],
        ]);

        $this->assertNull($finance->issueLeaseSchedule($user, $contract->fresh(), $version->fresh()));
        $this->assertSame(1, $contract->paymentSchedules()->count());
        $this->assertSame($manual->id, $contract->paymentSchedules()->firstOrFail()->id);
    }

    public function test_a_cancelled_schedule_does_not_block_activation(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit();
        $this->grant($user, ['cancel_contract_payment_schedule']);
        $contract = $this->leaseDraft($user, $unit, rent: 500, frequency: RentFrequency::Monthly, termMonths: 12, start: today());
        $cancelled = null;

        $this->signAndActivate($user, $contract, function (ContractVersion $approved) use ($user, $contract, &$cancelled): void {
            $finance = app(ContractFinancialService::class);
            $manual = $finance->createDraft($user, $contract, $approved, [
                'installments' => [['amount' => 6000, 'due_date' => today()->toDateString()]],
            ]);
            $cancelled = $finance->cancel($user, $manual, 'Wrong dates');
        });

        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
        $this->assertSame(ContractPaymentScheduleStatus::Cancelled, $cancelled->fresh()->status);

        $issued = $contract->paymentSchedules()->where('status', ContractPaymentScheduleStatus::Issued->value)->firstOrFail();
        $this->assertSame(2, (int) $issued->revision_number);
        $this->assertSame(12, $issued->installments()->count());
    }

    public function test_a_sale_is_never_given_a_rent_schedule(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit();
        $buyer = Party::create(['company_id' => $user->company_id, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);
        $contract = app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Sale,
            'unit_id' => $unit->id,
            'template_version_id' => $this->template($user, ContractType::Sale)->id,
            'total_amount' => 9000,
            'currency' => 'USD',
            'parties' => [['party_id' => $buyer->id, 'role' => 'buyer']],
            'terms' => [['term_type' => 'commercial', 'title' => 'Price', 'text' => 'Agreed price']],
        ]);
        $this->signAndActivate($user, $contract);

        $this->assertSame(0, $contract->paymentSchedules()->count());
    }

    public function test_a_lease_whose_term_is_not_whole_periods_activates_without_a_schedule(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit();
        $contract = $this->leaseDraft($user, $unit, rent: 900, frequency: RentFrequency::Quarterly, termMonths: 12, start: today());
        $version = $contract->versions()->firstOrFail();
        // Ten months is not a whole number of quarterly payments.
        $version->forceFill(['end_date' => today()->addMonths(10)->subDay()->toDateString()])->save();

        $this->assertNull(app(ContractFinancialService::class)->issueLeaseSchedule($user, $contract, $version->fresh()));
        $this->assertSame(0, $contract->paymentSchedules()->count());
    }

    public function test_a_lease_that_has_not_started_can_be_cancelled_from_its_page(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit();
        $this->grant($user, ['view_any_contract', 'end_contract']);
        $contract = $this->activateLease($user, $unit, rent: 500, frequency: RentFrequency::Monthly, termMonths: 12, start: today()->addDays(4));
        $this->actingAs($user);

        Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
            ->mountAction('endContract')
            ->assertSee(__('contract_domain.end.not_started', ['date' => today()->addDays(4)->format('d/m/Y')]))
            ->setActionData(['ended_at' => today()->toDateString(), 'reason' => 'Tenant withdrew'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(ContractStatus::Ended, $contract->fresh()->status);
        $this->assertSame('ready', $unit->fresh()->status);
    }

    public function test_a_started_lease_still_cannot_end_before_its_start(): void
    {
        [, $user, $unit] = $this->companyUserAndUnit();
        $this->grant($user, ['end_contract']);
        $contract = $this->activateLease($user, $unit, rent: 500, frequency: RentFrequency::Monthly, termMonths: 12, start: today()->subDays(10));

        try {
            app(ContractWorkflowService::class)->endContract($user, $contract, today()->subDays(11)->toDateString(), 'Too early');
            $this->fail('A started lease ended before its own start date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ended_at', $exception->errors());
        }

        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function activateLease(User $user, Unit $unit, int $rent, RentFrequency $frequency, int $termMonths, $start): Contract
    {
        $contract = $this->leaseDraft($user, $unit, $rent, $frequency, $termMonths, $start);
        $this->signAndActivate($user, $contract);

        return $contract->fresh();
    }

    private function leaseDraft(User $user, Unit $unit, int $rent, RentFrequency $frequency, int $termMonths, $start): Contract
    {
        return app(ContractDraftService::class)->create($user, [
            'contract_type' => ContractType::Lease,
            'unit_id' => $unit->id,
            'template_version_id' => $this->template($user, ContractType::Lease)->id,
            'rent_amount' => $rent,
            'rent_frequency' => $frequency->value,
            'currency' => 'USD',
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addMonths($termMonths)->subDay()->toDateString(),
            'parties' => [['party_id' => $this->tenant($user), 'role' => 'tenant']],
            'terms' => [['term_type' => 'commercial', 'title' => 'Rent', 'text' => 'Rent terms']],
        ]);
    }

    private function signAndActivate(User $user, Contract $contract, ?Closure $afterApproval = null): void
    {
        $workflow = app(ContractWorkflowService::class);
        $version = $contract->versions()->firstOrFail();
        $workflow->submit($user, $version);
        $workflow->approve($user, $version->fresh());
        if ($afterApproval) {
            $afterApproval($version->fresh());
        }

        $file = 'official-'.$contract->id.'.pdf';
        $document = Document::create([
            'documentable_type' => \App\Models\ContractVersion::class, 'documentable_id' => $version->id,
            'document_type' => 'official_approved_pdf', 'title' => $file, 'created_by' => $user->id,
        ]);
        Storage::disk(config('filesystems.default'))->put('contracts/'.$file, $file);
        $documentVersion = DocumentVersion::create([
            'company_id' => $contract->company_id, 'document_id' => $document->id, 'version_number' => 1,
            'file_name' => $file, 'file_path' => 'contracts/'.$file, 'uploaded_by' => $user->id,
        ]);

        foreach ($version->parties as $party) {
            $workflow->recordSignature($user, $version->fresh(), $party, $documentVersion, 'Signatory', 'manual');
        }
        $workflow->activate($user, $contract, $version->fresh());
    }

    private function tenant(User $user): int
    {
        return Party::create(['company_id' => $user->company_id, 'type' => 'person', 'name' => 'Tenant '.str()->random(4), 'is_active' => true])->id;
    }

    private function template(User $user, ContractType $type): ContractTemplateVersion
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

    /** @return array{0: Company, 1: User, 2: Unit} */
    private function companyUserAndUnit(): array
    {
        $company = Company::create(['name' => 'Lease Co '.str()->random(4), 'email' => str()->uuid().'@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->grant($user, [
            'create_contract', 'view_contract', 'submit_contract_version', 'approve_contract_version',
            'record_contract_signature', 'generate_contract_pdf', 'activate_contract',
            'view_contract_financials', 'create_contract_payment_schedule', 'page_RentRoll',
        ]);
        $location = Location::create(['company_id' => $company->id, 'name' => 'City', 'type' => 'city']);
        $property = Property::create(['company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Property', 'address' => 'Address']);
        $unit = Unit::create(['company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-101', 'status' => 'ready', 'type' => 'apartment']);

        return [$company, $user, $unit];
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);
    }
}
