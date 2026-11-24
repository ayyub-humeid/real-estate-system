<?php

namespace Tests\Feature;

use App\Enums\{ContractPaymentScheduleStatus, ContractStatus, ContractType, ContractVersionStatus};
use App\Filament\Exports\PaymentExporter;
use App\Models\{Company, Contract, ContractParty, ContractVersion, Location, Party, Payment, Plan, Property, Subscription, Unit, User};
use App\Services\ContractFinancialService;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class PaymentDirectoryExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_contract_display_name_shows_the_unit_place_and_type(): void
    {
        [$company, $actor] = $this->companyAndActor();
        $location = Location::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Amman', 'type' => 'city']);
        $property = Property::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'location_id' => $location->id, 'name' => 'Marina Tower', 'address' => 'Sea Road',
        ]);
        $unit = Unit::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'property_id' => $property->id, 'unit_number' => 'A-304', 'status' => 'draft',
        ]);

        $contract = Contract::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'contract_number' => 'CNT-77', 'contract_type' => ContractType::Sale,
            'status' => ContractStatus::Active, 'created_by' => $actor->id,
            'property_id' => $property->id, 'unit_id' => $unit->id,
        ]);

        $this->assertSame('A-304 · Marina Tower · Sale', $contract->displayName());
        $this->assertSame('A-304 · Marina Tower · Sale (CNT-77)', $contract->displayNameWithNumber());
    }

    public function test_a_contract_with_no_unit_or_place_still_carries_its_number(): void
    {
        [$company, $actor] = $this->companyAndActor();
        $contract = Contract::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'contract_number' => 'CNT-BARE', 'contract_type' => ContractType::Sale,
            'status' => ContractStatus::Active, 'created_by' => $actor->id,
        ]);

        // "Sale" alone would not identify anything, so the picker label keeps the number.
        $this->assertSame('Sale', $contract->displayName());
        $this->assertSame('Sale (CNT-BARE)', $contract->displayNameWithNumber());
    }

    public function test_export_keeps_the_amount_unformatted_with_currency_in_its_own_column(): void
    {
        [$company, $actor, $payment] = $this->recordedPayment();

        $row = $this->exportRow($actor, $payment, ['amount', 'currency', 'payment_date', 'status', 'direction']);

        // No thousands separator and no symbol: "1,200.00 USD" in a cell cannot be summed.
        $this->assertSame('500', $row[0]);
        $this->assertSame('USD', $row[1]);
        $this->assertSame('2026-11-01', $row[2]);
        $this->assertSame('Completed', $row[3]);
        $this->assertSame('Incoming', $row[4]);
    }

    public function test_money_columns_are_declared_numeric_but_identifiers_are_not(): void
    {
        $numeric = PaymentExporter::numericColumns();

        $this->assertContains('amount', $numeric);
        $this->assertContains('project_amount', $numeric);
        $this->assertContains('exchange_rate_to_project', $numeric);

        // Casting these would destroy a cheque number such as "000912".
        $this->assertNotContains('reference_number', $numeric);
        $this->assertNotContains('contract.contract_number', $numeric);
    }

    public function test_the_xlsx_writer_casts_money_cells_to_numbers_and_leaves_identifiers_as_text(): void
    {
        $job = new \App\Jobs\CreateNumericAwareXlsxFile(
            new Export(['exporter' => PaymentExporter::class]),
            array_fill_keys(['payment_date', 'reference_number', 'amount', 'currency'], true),
            [],
        );

        $positions = (new \ReflectionMethod($job, 'numericColumnPositions'))->invoke($job);

        // Only "amount" (index 2) is numeric; reference_number at index 1 stays text.
        $this->assertSame([2], $positions);
    }

    public function test_export_resolves_the_descriptive_contract_subject(): void
    {
        [$company, $actor, $payment, $contract] = $this->recordedPayment();

        $row = $this->exportRow($actor, $payment, ['contract.contract_number', 'contract_subject', 'party.name', 'recordedBy.name']);

        $this->assertSame($contract->contract_number, $row[0]);
        $this->assertStringContainsString('Sale', $row[1]);
        $this->assertSame('Finance Buyer', $row[2]);
        $this->assertSame($actor->name, $row[3]);
    }

    public function test_the_default_export_covers_the_columns_finance_needs_for_reconciliation(): void
    {
        $enabled = collect(PaymentExporter::getColumns())
            ->filter(fn ($column): bool => $column->isEnabledByDefault())
            ->map(fn ($column): string => $column->getName())
            ->values()
            ->all();

        foreach (['payment_date', 'reference_number', 'direction', 'status', 'amount', 'currency', 'party.name', 'contract.contract_number', 'payment_method', 'recordedBy.name'] as $required) {
            $this->assertContains($required, $enabled, "The default export must include {$required}.");
        }

        // System metadata stays off by default so the sheet opens clean.
        $this->assertNotContains('id', $enabled);
        $this->assertNotContains('created_at', $enabled);
    }

    public function test_recording_a_payment_settles_the_installment_and_leaves_an_audit_trail(): void
    {
        [$company, $actor, $payment, $contract, $schedule] = $this->recordedPayment();

        $details = collect(app(ContractFinancialService::class)->installmentFinancialDetails($schedule));
        $first = $details->firstWhere('sequence', 1);

        $this->assertSame('500.0000', $first['paid']);
        $this->assertSame('paid', $first['payment_status']);
        $this->assertSame($actor->id, $payment->recorded_by);
        $this->assertSame('completed', $payment->status);
        $this->assertSame('contract_receipt', $payment->payment_scope);
        $this->assertDatabaseHas('contract_financial_events', ['contract_id' => $contract->id, 'event_type' => 'payment_recorded']);
    }

    /** @return array{0: Company, 1: User} */
    private function companyAndActor(): array
    {
        $company = Company::create(['name' => 'Export Co '.str()->uuid(), 'email' => str()->uuid().'@example.test', 'is_active' => true]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => Plan::create(['name' => 'T'.str()->uuid(), 'slug' => 't-'.str()->uuid(), 'price' => 1, 'billing_cycle' => 'monthly', 'features' => [], 'is_active' => true])->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        $actor = User::factory()->create(['company_id' => $company->id]);
        $actor->assignRole(Role::findOrCreate('export_test_role', 'web'));

        return [$company, $actor];
    }

    /** @return array{0: Company, 1: User, 2: Payment, 3: Contract, 4: \App\Models\ContractPaymentSchedule} */
    private function recordedPayment(): array
    {
        [$company, $actor] = $this->companyAndActor();
        $this->grant($actor, ['create_contract_payment_schedule', 'submit_contract_payment_schedule', 'approve_contract_payment_schedule', 'record_contract_payment', 'allocate_contract_payment']);

        $contract = Contract::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'contract_number' => 'CNT-'.str()->uuid(), 'contract_type' => ContractType::Sale,
            'status' => ContractStatus::Active, 'created_by' => $actor->id,
        ]);
        $version = ContractVersion::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'contract_id' => $contract->id, 'version_number' => 1,
            'status' => ContractVersionStatus::Signed, 'total_amount' => 1000, 'currency' => 'USD', 'created_by' => $actor->id,
        ]);
        $buyer = Party::withoutGlobalScopes()->create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Finance Buyer', 'is_active' => true]);
        ContractParty::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'contract_version_id' => $version->id, 'party_id' => $buyer->id,
            'role' => 'buyer', 'snapshot' => ['name' => $buyer->name],
        ]);

        $service = app(ContractFinancialService::class);
        $schedule = $service->createDraft($actor, $contract, $version, [
            'currency' => 'USD',
            'installments' => [
                ['kind' => 'installment', 'amount' => 500, 'due_date' => '2027-01-31'],
                ['kind' => 'installment', 'amount' => 500, 'due_date' => '2027-02-28'],
            ],
        ]);
        $service->submit($actor, $schedule);
        $service->issue($actor, $schedule->fresh());
        $schedule = $schedule->fresh();

        $this->assertSame(ContractPaymentScheduleStatus::Issued, $schedule->status);

        $installment = collect($service->outstandingInstallments($schedule))->first();

        // The same payload shape the payment directory's record-payment form submits.
        $payment = $service->recordReceipt($actor, $schedule, [
            'party_id' => $buyer->id,
            'amount' => $installment['outstanding'],
            'currency' => 'USD',
            'payment_date' => '2026-11-01',
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRF-9001',
            'allocations' => [[
                'installment_id' => $installment['id'],
                'allocated_amount' => $installment['outstanding'],
                'currency' => 'USD',
            ]],
        ]);

        return [$company, $actor, $payment, $contract, $schedule];
    }

    private function exportRow(User $actor, Payment $payment, array $columns): array
    {
        $export = Export::create([
            'file_disk' => 'local',
            'file_name' => 'payments-test',
            'exporter' => PaymentExporter::class,
            'total_rows' => 1,
            'user_id' => $actor->id,
        ]);

        $exporter = new PaymentExporter($export, array_fill_keys($columns, true), []);

        return $exporter($payment->fresh());
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);
    }
}
