<?php

namespace Tests\Feature;

use App\Enums\{ContractPaymentScheduleStatus, ContractStatus, ContractType, ContractVersionStatus};
use App\Models\{Company, Contract, ContractParty, ContractVersion, Party, User};
use App\Services\{ContractFinancialReminderService, ContractFinancialService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContractFinancialEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_generation_keeps_down_payment_and_installment_total_exact(): void
    {
        [$company, $actor, $contract, $version] = $this->activeContract(1200);
        $this->grant($actor, ['create_contract_payment_schedule']);

        $schedule = app(ContractFinancialService::class)->createGeneratedDraft($actor, $contract, $version, [
            'generation_mode' => 'count',
            'number_of_installments' => 12,
            'interval_months' => 2,
            'first_installment_date' => '2027-01-31',
            'down_payment_amount' => 120,
            'down_payment_due_date' => '2026-12-01',
        ]);

        $this->assertSame(ContractPaymentScheduleStatus::Draft, $schedule->status);
        $this->assertSame(13, $schedule->installments()->count());
        $this->assertSame('1200.0000', $schedule->installments()->sum('amount'));
        $this->assertSame('2027-01-31', $schedule->installments()->orderBy('sequence')->skip(1)->firstOrFail()->due_date->toDateString());
        $this->assertSame('2027-03-31', $schedule->installments()->orderBy('sequence')->skip(2)->firstOrFail()->due_date->toDateString());
    }

    public function test_duration_generation_requires_exact_interval_division(): void
    {
        [, $actor, $contract, $version] = $this->activeContract(1000);
        $this->grant($actor, ['create_contract_payment_schedule']);

        $this->expectException(ValidationException::class);
        app(ContractFinancialService::class)->createGeneratedDraft($actor, $contract, $version, [
            'generation_mode' => 'duration',
            'duration' => 25,
            'duration_unit' => 'months',
            'interval_months' => 3,
            'first_installment_date' => '2027-01-01',
        ]);
    }

    public function test_issued_schedule_accepts_partial_multi_installment_receipt_without_over_allocation(): void
    {
        [, $actor, $contract, $version, $buyer] = $this->activeContract(1000);
        $this->grant($actor, [
            'create_contract_payment_schedule', 'submit_contract_payment_schedule', 'approve_contract_payment_schedule',
            'record_contract_payment', 'allocate_contract_payment', 'view_contract_financials',
        ]);
        $service = app(ContractFinancialService::class);
        $schedule = $service->createDraft($actor, $contract, $version, [
            'installments' => [
                ['amount' => '400.0000', 'due_date' => '2027-01-01'],
                ['amount' => '600.0000', 'due_date' => '2027-02-01'],
            ],
        ]);
        $service->submit($actor, $schedule);
        $schedule = $service->issue($actor, $schedule->fresh());
        $installments = $schedule->installments()->orderBy('sequence')->get();

        $payment = $service->recordReceipt($actor, $schedule, [
            'party_id' => $buyer->id,
            'amount' => '500.0000',
            'currency' => 'USD',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'bank_transfer',
            'idempotency_key' => 'receipt-1',
            'allocations' => [
                ['installment_id' => $installments[0]->id, 'allocated_amount' => '400.0000', 'currency' => 'USD'],
                ['installment_id' => $installments[1]->id, 'allocated_amount' => '100.0000', 'currency' => 'USD'],
            ],
        ]);

        $this->assertSame('incoming', $payment->direction);
        $this->assertSame(2, $payment->contractAllocations()->count());
        $this->assertSame('500.0000', $service->summary($contract)['received']);
        $this->assertSame('500.0000', $service->summary($contract)['outstanding']);

        $sameRequest = $service->recordReceipt($actor, $schedule, [
            'party_id' => $buyer->id,
            'amount' => '500.0000',
            'currency' => 'USD',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'bank_transfer',
            'idempotency_key' => 'receipt-1',
            'allocations' => [],
        ]);
        $this->assertSame($payment->id, $sameRequest->id);
        $this->assertSame(1, $schedule->payments()->count());

        try {
            $service->recordReceipt($actor, $schedule, [
                'party_id' => $buyer->id, 'amount' => '700.0000', 'currency' => 'USD',
                'payment_date' => today()->toDateString(), 'payment_method' => 'cash',
                'allocations' => [['installment_id' => $installments[1]->id, 'allocated_amount' => '700.0000', 'currency' => 'USD']],
            ]);
            $this->fail('Over-allocation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocated_amount', $exception->errors());
        }
    }

    public function test_reschedule_keeps_partial_receipt_and_creates_new_outstanding_revision(): void
    {
        [, $actor, $contract, $version, $buyer] = $this->activeContract(100);
        $this->grant($actor, [
            'create_contract_payment_schedule', 'submit_contract_payment_schedule', 'approve_contract_payment_schedule',
            'record_contract_payment', 'allocate_contract_payment', 'reschedule_contract_payment_schedule',
        ]);
        $service = app(ContractFinancialService::class);
        $schedule = $service->createDraft($actor, $contract, $version, ['installments' => [
            ['amount' => '50.0000', 'due_date' => '2027-01-01'],
            ['amount' => '50.0000', 'due_date' => '2027-02-01'],
        ]]);
        $schedule = $service->issue($actor, $service->submit($actor, $schedule));
        $first = $schedule->installments()->orderBy('sequence')->firstOrFail();
        $service->recordReceipt($actor, $schedule, [
            'party_id' => $buyer->id, 'amount' => '25.0000', 'currency' => 'USD',
            'payment_date' => today()->toDateString(), 'payment_method' => 'cash',
            'allocations' => [['installment_id' => $first->id, 'allocated_amount' => '25.0000', 'currency' => 'USD']],
        ]);

        $revision = $service->reschedule($actor, $schedule->fresh(), [
            'reason' => 'Customer requested a later payment plan.',
            'installments' => [['amount' => '75.0000', 'due_date' => '2027-06-01']],
        ]);

        $this->assertSame(ContractPaymentScheduleStatus::Superseded, $schedule->fresh()->status);
        $this->assertSame(2, $revision->revision_number);
        $this->assertSame('75.0000', $revision->total_amount);
        $this->assertSame('25.0000', $service->summary($contract)['received']);
    }

    public function test_an_active_schedule_cannot_be_replaced_by_a_competing_draft(): void
    {
        [, $actor, $contract, $version] = $this->activeContract(100);
        $this->grant($actor, [
            'create_contract_payment_schedule', 'submit_contract_payment_schedule', 'approve_contract_payment_schedule',
        ]);
        $service = app(ContractFinancialService::class);
        $schedule = $service->createDraft($actor, $contract, $version, ['installments' => [
            ['amount' => '100.0000', 'due_date' => '2027-01-01'],
        ]]);
        $service->issue($actor, $service->submit($actor, $schedule));

        try {
            $service->createDraft($actor, $contract, $version, ['installments' => [
                ['amount' => '100.0000', 'due_date' => '2027-02-01'],
            ]]);
            $this->fail('A competing active payment schedule was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schedule', $exception->errors());
        }
    }

    public function test_a_user_from_another_company_cannot_record_a_contract_receipt(): void
    {
        [, $actor, $contract, $version, $buyer] = $this->activeContract(100);
        $this->grant($actor, [
            'create_contract_payment_schedule', 'submit_contract_payment_schedule', 'approve_contract_payment_schedule',
        ]);
        $service = app(ContractFinancialService::class);
        $schedule = $service->createDraft($actor, $contract, $version, ['installments' => [
            ['amount' => '100.0000', 'due_date' => '2027-01-01'],
        ]]);
        $schedule = $service->issue($actor, $service->submit($actor, $schedule));

        $otherCompany = Company::create(['name' => 'Other Co '.str()->uuid(), 'email' => str()->uuid().'@example.test', 'is_active' => true]);
        $outsider = User::factory()->create(['company_id' => $otherCompany->id]);
        $this->grant($outsider, ['record_contract_payment', 'allocate_contract_payment']);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->recordReceipt($outsider, $schedule, [
            'party_id' => $buyer->id, 'amount' => '100.0000', 'currency' => 'USD',
            'payment_date' => today()->toDateString(), 'payment_method' => 'cash',
            'allocations' => [['installment_id' => $schedule->installments()->firstOrFail()->id, 'allocated_amount' => '100.0000', 'currency' => 'USD']],
        ]);
    }

    public function test_due_reminder_creates_one_idempotent_event_for_the_assigned_responsible_employee(): void
    {
        [, $actor, $contract, $version] = $this->activeContract(100);
        $this->grant($actor, [
            'create_contract_payment_schedule', 'submit_contract_payment_schedule', 'approve_contract_payment_schedule',
            'view_contract_financials',
        ]);
        $service = app(ContractFinancialService::class);
        $schedule = $service->createDraft($actor, $contract, $version, [
            'assigned_to' => $actor->id,
            'installments' => [['amount' => '100.0000', 'due_date' => today()->toDateString()]],
        ]);
        $schedule = $service->issue($actor, $service->submit($actor, $schedule));

        $reminders = app(ContractFinancialReminderService::class);
        $this->assertSame(1, $reminders->send());
        $this->assertSame(0, $reminders->send());
        $this->assertDatabaseHas('contract_financial_events', [
            'company_id' => $contract->company_id,
            'contract_id' => $contract->id,
            'contract_payment_schedule_id' => $schedule->id,
            'event_type' => 'installment_due',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $actor->id,
            'type' => \App\Notifications\ContractFinancialNotification::class,
        ]);
    }

    /** @return array{0:Company,1:User,2:Contract,3:ContractVersion,4:Party} */
    private function activeContract(int $amount): array
    {
        $company = Company::create(['name' => 'Finance Co '.str()->uuid(), 'email' => str()->uuid().'@example.test', 'is_active' => true]);
        $actor = User::factory()->create(['company_id' => $company->id]);
        $contract = Contract::create([
            'company_id' => $company->id, 'contract_number' => 'CNT-'.str()->uuid(), 'contract_type' => ContractType::Sale,
            'status' => ContractStatus::Active, 'created_by' => $actor->id,
        ]);
        $version = ContractVersion::create([
            'company_id' => $company->id, 'contract_id' => $contract->id, 'version_number' => 1,
            'status' => ContractVersionStatus::Signed, 'total_amount' => $amount, 'currency' => 'USD', 'created_by' => $actor->id,
        ]);
        $buyer = Party::create(['company_id' => $company->id, 'type' => 'person', 'name' => 'Finance Buyer', 'is_active' => true]);
        ContractParty::create(['company_id' => $company->id, 'contract_version_id' => $version->id, 'party_id' => $buyer->id, 'role' => 'buyer', 'snapshot' => ['name' => $buyer->name]]);

        return [$company, $actor, $contract, $version, $buyer];
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);
    }
}
