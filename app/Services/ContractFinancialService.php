<?php

namespace App\Services;

use App\Enums\{
    ContractInstallmentKind,
    ContractPaymentScheduleStatus,
    ContractStatus,
    ContractVersionStatus,
};
use App\Models\{
    Contract,
    ContractFinancialEvent,
    ContractInstallment,
    ContractParty,
    ContractPaymentAllocation,
    ContractPaymentSchedule,
    ContractVersion,
    Party,
    Payment,
    User,
};
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns the contract receivables workflow. It deliberately reuses the canonical
 * Payment ledger while keeping the Phase 04 project-cost allocation schema intact.
 */
class ContractFinancialService
{
    private const MONEY_SCALE = 4;

    public function createDraft(User $actor, Contract $contract, ContractVersion $version, array $data): ContractPaymentSchedule
    {
        $this->can($actor, 'create_contract_payment_schedule');

        return DB::transaction(function () use ($actor, $contract, $version, $data): ContractPaymentSchedule {
            $lockedContract = Contract::withoutGlobalScopes()->lockForUpdate()->findOrFail($contract->id);
            $lockedVersion = ContractVersion::withoutGlobalScopes()->lockForUpdate()->findOrFail($version->id);
            $this->sameCompany($actor, $lockedContract->company_id);
            $this->assertVersionForSchedule($lockedContract, $lockedVersion);

            $schedules = ContractPaymentSchedule::withoutGlobalScopes()
                ->where('contract_id', $lockedContract->id)
                ->lockForUpdate()
                ->get();
            if ($schedules->contains(fn (ContractPaymentSchedule $schedule): bool => in_array($schedule->status, [ContractPaymentScheduleStatus::Draft, ContractPaymentScheduleStatus::PendingApproval, ContractPaymentScheduleStatus::Issued], true))) {
                $this->invalid('schedule', __('contract_finance.errors.schedule_exists'));
            }

            $total = $this->money($lockedVersion->total_amount, 'total_amount');
            $currency = strtoupper((string) $lockedVersion->currency);
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                $this->invalid('currency', __('contract_finance.errors.currency_mismatch'));
            }

            $schedule = ContractPaymentSchedule::withoutGlobalScopes()->create([
                'company_id' => $lockedContract->company_id,
                'contract_id' => $lockedContract->id,
                'contract_version_id' => $lockedVersion->id,
                'revision_number' => ((int) $schedules->max('revision_number')) + 1,
                'status' => ContractPaymentScheduleStatus::Draft->value,
                'currency' => $currency,
                'total_amount' => $this->string($total),
                'assigned_to' => $this->assignedUserId($lockedContract->company_id, $data['assigned_to'] ?? null),
                'reason' => filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null,
                'created_by' => $actor->id,
            ]);

            if (! empty($data['installments'])) {
                $this->replaceDraftInstallmentsLocked($actor, $schedule, $data['installments'], true);
            }

            $this->event($actor, $lockedContract, 'schedule_created', 'schedule:'.$schedule->id.':created', $schedule);
            DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForSchedule($schedule->id, 'schedule_created'));

            return $schedule->fresh(['installments', 'contractVersion']);
        });
    }

    public function createGeneratedDraft(User $actor, Contract $contract, ContractVersion $version, array $data): ContractPaymentSchedule
    {
        $total = $this->money($version->total_amount, 'total_amount');
        $currency = strtoupper((string) $version->currency);
        $mode = (string) ($data['generation_mode'] ?? 'count');
        $lines = match ($mode) {
            'count' => $this->generatedLinesByCount($total, $currency, $data),
            'duration' => $this->generatedLinesByDuration($total, $currency, $data),
            default => throw ValidationException::withMessages(['generation_mode' => __('contract_finance.errors.count_invalid')]),
        };

        return $this->createDraft($actor, $contract, $version, [...$data, 'installments' => $lines]);
    }

    public function addInstallment(User $actor, ContractPaymentSchedule $schedule, array $data): ContractInstallment
    {
        $this->can($actor, 'update_contract_payment_schedule');

        return DB::transaction(function () use ($actor, $schedule, $data): ContractInstallment {
            $locked = $this->lockedSchedule($schedule);
            $this->sameCompany($actor, $locked->company_id);
            $this->assertDraft($locked);
            $line = $this->normaliseLine($data, $locked->currency);
            $next = ((int) $locked->installments()->max('sequence')) + 1;

            return ContractInstallment::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'contract_payment_schedule_id' => $locked->id,
                'sequence' => $next,
                ...$line,
            ]);
        });
    }

    public function updateInstallment(User $actor, ContractInstallment $installment, array $data): ContractInstallment
    {
        $this->can($actor, 'update_contract_payment_schedule');

        return DB::transaction(function () use ($actor, $installment, $data): ContractInstallment {
            $locked = ContractInstallment::withoutGlobalScopes()->lockForUpdate()->findOrFail($installment->id);
            $schedule = $this->lockedSchedule($locked->schedule);
            $this->sameCompany($actor, $schedule->company_id);
            $this->assertDraft($schedule);
            $locked->update($this->normaliseLine([...$locked->only(['kind', 'amount', 'due_date']), ...$data], $schedule->currency));

            return $locked->refresh();
        });
    }

    public function removeInstallment(User $actor, ContractInstallment $installment): void
    {
        $this->can($actor, 'update_contract_payment_schedule');

        DB::transaction(function () use ($actor, $installment): void {
            $locked = ContractInstallment::withoutGlobalScopes()->lockForUpdate()->findOrFail($installment->id);
            $schedule = $this->lockedSchedule($locked->schedule);
            $this->sameCompany($actor, $schedule->company_id);
            $this->assertDraft($schedule);
            $locked->delete();
        });
    }

    public function replaceDraftInstallments(User $actor, ContractPaymentSchedule $schedule, array $lines): ContractPaymentSchedule
    {
        $this->can($actor, 'update_contract_payment_schedule');

        return DB::transaction(function () use ($actor, $schedule, $lines): ContractPaymentSchedule {
            $locked = $this->lockedSchedule($schedule);
            $this->sameCompany($actor, $locked->company_id);
            $this->replaceDraftInstallmentsLocked($actor, $locked, $lines, true);

            return $locked->fresh('installments');
        });
    }

    public function submit(User $actor, ContractPaymentSchedule $schedule): ContractPaymentSchedule
    {
        $this->can($actor, 'submit_contract_payment_schedule');

        return DB::transaction(function () use ($actor, $schedule): ContractPaymentSchedule {
            $locked = $this->lockedSchedule($schedule);
            $this->sameCompany($actor, $locked->company_id);
            $this->assertDraft($locked);
            $this->assertExactScheduleTotal($locked);
            $locked->update(['status' => ContractPaymentScheduleStatus::PendingApproval, 'submitted_by' => $actor->id, 'submitted_at' => now()]);
            $this->event($actor, $locked->contract, 'schedule_submitted', 'schedule:'.$locked->id.':submitted', $locked);
            DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForSchedule($locked->id, 'schedule_submitted'));

            return $locked->refresh();
        });
    }

    public function issue(User $actor, ContractPaymentSchedule $schedule): ContractPaymentSchedule
    {
        $this->can($actor, 'approve_contract_payment_schedule');

        return DB::transaction(function () use ($actor, $schedule): ContractPaymentSchedule {
            $locked = $this->lockedSchedule($schedule);
            $this->sameCompany($actor, $locked->company_id);
            if ($locked->status !== ContractPaymentScheduleStatus::PendingApproval) {
                $this->invalid('schedule', __('contract_finance.errors.pending_only'));
            }
            if ($locked->contract->status !== ContractStatus::Active) {
                $this->invalid('contract', __('contract_finance.errors.schedule_requires_active_contract'));
            }
            $this->assertExactScheduleTotal($locked);
            $locked->update(['status' => ContractPaymentScheduleStatus::Issued, 'issued_by' => $actor->id, 'issued_at' => now()]);
            $this->event($actor, $locked->contract, 'schedule_issued', 'schedule:'.$locked->id.':issued', $locked);
            DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForSchedule($locked->id, 'schedule_issued'));

            return $locked->refresh();
        });
    }

    public function cancel(User $actor, ContractPaymentSchedule $schedule, string $reason): ContractPaymentSchedule
    {
        $this->can($actor, 'cancel_contract_payment_schedule');

        return DB::transaction(function () use ($actor, $schedule, $reason): ContractPaymentSchedule {
            $locked = $this->lockedSchedule($schedule);
            $this->sameCompany($actor, $locked->company_id);
            if (blank($reason)) {
                $this->invalid('cancellation_reason', __('contract_finance.errors.cancellation_reason_required'));
            }
            if ($locked->installments()->whereHas('allocations')->exists()) {
                $this->invalid('schedule', __('contract_finance.errors.schedule_has_receipts'));
            }
            if (! in_array($locked->status, [ContractPaymentScheduleStatus::Draft, ContractPaymentScheduleStatus::PendingApproval, ContractPaymentScheduleStatus::Issued], true)) {
                $this->invalid('schedule', __('contract_finance.errors.issued_only'));
            }
            $locked->installments()->update(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => trim($reason)]);
            $locked->update(['status' => ContractPaymentScheduleStatus::Cancelled, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => trim($reason)]);
            $this->event($actor, $locked->contract, 'schedule_cancelled', 'schedule:'.$locked->id.':cancelled', $locked, ['reason' => trim($reason)]);
            DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForSchedule($locked->id, 'schedule_cancelled'));

            return $locked->refresh();
        });
    }

    public function recordReceipt(User $actor, ContractPaymentSchedule $schedule, array $data): Payment
    {
        $this->can($actor, 'record_contract_payment');
        $this->can($actor, 'allocate_contract_payment');

        return DB::transaction(function () use ($actor, $schedule, $data): Payment {
            $lockedSchedule = $this->lockedSchedule($schedule);
            $this->sameCompany($actor, $lockedSchedule->company_id);
            $this->assertIssued($lockedSchedule);
            $party = $this->contractParty($lockedSchedule, $data['party_id'] ?? null);
            $amount = $this->money($data['amount'] ?? null, 'amount');
            $currency = strtoupper((string) ($data['currency'] ?? ''));
            if ($currency !== $lockedSchedule->currency) {
                $this->invalid('currency', __('contract_finance.errors.currency_mismatch'));
            }
            $paymentDate = $this->date($data['payment_date'] ?? null, 'payment_date');
            if ($paymentDate->gt(today())) {
                $this->invalid('payment_date', __('contract_finance.errors.payment_date_future'));
            }
            $key = trim((string) ($data['idempotency_key'] ?? '')) ?: Str::uuid()->toString();
            $existing = Payment::withoutGlobalScopes()->where('company_id', $lockedSchedule->company_id)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->contract_id !== $lockedSchedule->contract_id || $existing->payment_scope !== 'contract_receipt') {
                    $this->invalid('idempotency_key', __('contract_finance.errors.idempotency_conflict'));
                }
                return $existing;
            }

            $payment = Payment::withoutGlobalScopes()->create([
                'company_id' => $lockedSchedule->company_id,
                'contract_id' => $lockedSchedule->contract_id,
                'contract_payment_schedule_id' => $lockedSchedule->id,
                'party_id' => $party->id,
                'direction' => 'incoming',
                'payment_scope' => 'contract_receipt',
                'idempotency_key' => $key,
                'amount' => $this->string($amount),
                'currency' => $currency,
                'project_amount' => null,
                'payment_date' => $paymentDate->toDateString(),
                'reference_number' => filled($data['reference_number'] ?? null) ? trim((string) $data['reference_number']) : null,
                'payment_method' => filled($data['payment_method'] ?? null) ? trim((string) $data['payment_method']) : null,
                'status' => 'completed',
                'completed_at' => now(),
                'recorded_by' => $actor->id,
            ]);

            $this->allocateLocked($actor, $payment, $lockedSchedule, $data['allocations'] ?? []);
            $this->event($actor, $lockedSchedule->contract, 'payment_recorded', 'payment:'.$payment->id.':recorded', $lockedSchedule, payment: $payment);
            DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForPayment($payment->id, 'payment_recorded'));
            if ($this->isSettled($lockedSchedule->contract)) {
                $settled = $this->event($actor, $lockedSchedule->contract, 'contract_settled', 'contract:'.$lockedSchedule->contract_id.':settled', $lockedSchedule);
                DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForEvent($settled->id));
            }

            return $payment->fresh('contractAllocations');
        });
    }

    public function reversePayment(User $actor, Payment $original, array $data): Payment
    {
        $this->can($actor, 'reverse_contract_payment');

        return DB::transaction(function () use ($actor, $original, $data): Payment {
            $lockedOriginal = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($original->id);
            $this->sameCompany($actor, $lockedOriginal->company_id);
            if ($lockedOriginal->payment_scope !== 'contract_receipt' || $lockedOriginal->status !== 'completed' || $lockedOriginal->direction !== 'incoming' || ! $lockedOriginal->contract_payment_schedule_id) {
                $this->invalid('payment', __('contract_finance.errors.payment_invalid'));
            }
            $schedule = $this->lockedSchedule($lockedOriginal->contractPaymentSchedule);
            $this->assertIssued($schedule);
            $amount = $this->money($data['amount'] ?? null, 'amount');
            $paymentDate = $this->date($data['payment_date'] ?? now(), 'payment_date');
            if ($paymentDate->gt(today())) {
                $this->invalid('payment_date', __('contract_finance.errors.payment_date_future'));
            }
            $key = trim((string) ($data['idempotency_key'] ?? '')) ?: Str::uuid()->toString();
            $existing = Payment::withoutGlobalScopes()->where('company_id', $schedule->company_id)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->reversal_of_payment_id !== $lockedOriginal->id) {
                    $this->invalid('idempotency_key', __('contract_finance.errors.idempotency_conflict'));
                }
                return $existing;
            }
            $payment = Payment::withoutGlobalScopes()->create([
                'company_id' => $schedule->company_id,
                'contract_id' => $schedule->contract_id,
                'contract_payment_schedule_id' => $schedule->id,
                'party_id' => $lockedOriginal->party_id,
                'direction' => 'outgoing',
                'payment_scope' => 'contract_refund',
                'idempotency_key' => $key,
                'amount' => $this->string($amount),
                'currency' => $schedule->currency,
                'project_amount' => null,
                'payment_date' => $paymentDate->toDateString(),
                'reference_number' => filled($data['reference_number'] ?? null) ? trim((string) $data['reference_number']) : null,
                'payment_method' => filled($data['payment_method'] ?? null) ? trim((string) $data['payment_method']) : null,
                'status' => 'completed',
                'completed_at' => now(),
                'recorded_by' => $actor->id,
                'reversal_of_payment_id' => $lockedOriginal->id,
            ]);
            $this->allocateLocked($actor, $payment, $schedule, $data['allocations'] ?? []);
            $this->event($actor, $schedule->contract, 'payment_reversed', 'payment:'.$payment->id.':reversed', $schedule, payment: $payment, payload: ['reversal_of_payment_id' => $lockedOriginal->id]);
            DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForPayment($payment->id, 'payment_reversed'));

            return $payment->fresh('contractAllocations');
        });
    }

    public function reschedule(User $actor, ContractPaymentSchedule $schedule, array $data): ContractPaymentSchedule
    {
        $this->can($actor, 'reschedule_contract_payment_schedule');

        return DB::transaction(function () use ($actor, $schedule, $data): ContractPaymentSchedule {
            $locked = $this->lockedSchedule($schedule);
            $this->sameCompany($actor, $locked->company_id);
            $this->assertIssued($locked);
            $oldInstallments = ContractInstallment::withoutGlobalScopes()
                ->where('contract_payment_schedule_id', $locked->id)
                ->where('status', 'scheduled')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $outstanding = collect();
            foreach ($oldInstallments as $installment) {
                $balance = $this->money($installment->amount, 'amount')
                    ->minus($this->installmentNetBalance($installment));
                if ($balance->isGreaterThan(BigDecimal::zero())) {
                    $outstanding->push(['installment' => $installment, 'amount' => $balance]);
                }
            }
            $total = $this->sum($outstanding->pluck('amount')->all());
            if ($total->isZero()) {
                $this->invalid('schedule', __('contract_finance.errors.no_outstanding_balance'));
            }

            $lines = $this->normaliseLines($data['installments'] ?? [], $locked->currency, $total, true);
            // The source schedule itself is already locked above. PostgreSQL
            // correctly rejects FOR UPDATE on MAX(), and that aggregate does
            // not need an additional row lock once the source is serialized.
            $next = ContractPaymentSchedule::withoutGlobalScopes()
                ->where('contract_id', $locked->contract_id)
                ->max('revision_number') + 1;
            $replacement = ContractPaymentSchedule::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'contract_id' => $locked->contract_id,
                'contract_version_id' => $locked->contract_version_id,
                'revision_number' => $next,
                'supersedes_schedule_id' => $locked->id,
                'status' => ContractPaymentScheduleStatus::Draft->value,
                'currency' => $locked->currency,
                'total_amount' => $this->string($total),
                'assigned_to' => $this->assignedUserId($locked->company_id, $data['assigned_to'] ?? $locked->assigned_to),
                'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
                'created_by' => $actor->id,
            ]);
            foreach ($lines as $index => $line) {
                ContractInstallment::withoutGlobalScopes()->create([
                    'company_id' => $replacement->company_id,
                    'contract_payment_schedule_id' => $replacement->id,
                    'replaces_installment_id' => $outstanding[$index]['installment']->id ?? null,
                    'sequence' => $index + 1,
                    ...$line,
                ]);
            }
            $oldInstallments->filter(fn (ContractInstallment $item) => $this->money($item->amount, 'amount')
                ->minus($this->installmentNetBalance($item))
                ->isGreaterThan(BigDecimal::zero()))
                ->each(fn (ContractInstallment $item) => $item->update(['status' => 'replaced']));
            $locked->update(['status' => ContractPaymentScheduleStatus::Superseded]);
            $this->event($actor, $locked->contract, 'schedule_rescheduled', 'schedule:'.$replacement->id.':rescheduled', $replacement, payload: ['supersedes_schedule_id' => $locked->id]);
            DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForSchedule($replacement->id, 'schedule_rescheduled'));

            return $replacement->fresh('installments');
        });
    }

    /** @return array{scheduled:string,received:string,outstanding:string,due:string,overdue:string} */
    public function summary(Contract $contract): array
    {
        $schedules = ContractPaymentSchedule::withoutGlobalScopes()
            ->where('contract_id', $contract->id)
            ->whereIn('status', [ContractPaymentScheduleStatus::Issued->value, ContractPaymentScheduleStatus::Superseded->value])
            ->with('installments')
            ->get();
        $scheduled = BigDecimal::zero();
        $received = BigDecimal::zero();
        $due = BigDecimal::zero();
        $overdue = BigDecimal::zero();
        foreach ($schedules as $schedule) {
            foreach ($schedule->installments->where('status', '!=', 'cancelled') as $installment) {
                $net = $this->installmentNetBalance($installment);
                // A replaced installment has had only its unpaid balance moved
                // into the next revision. Retain its settled portion in the
                // history/total, but never count the replaced balance twice.
                if ($installment->status === 'replaced') {
                    $scheduled = $scheduled->plus($net);
                    $received = $received->plus($net);
                    continue;
                }
                $scheduled = $scheduled->plus($this->money($installment->amount, 'amount'));
                $received = $received->plus($net);
                $outstanding = $this->money($installment->amount, 'amount')->minus($net);
                if ($installment->due_date?->lte(today()) && $outstanding->isGreaterThan(BigDecimal::zero())) {
                    $due = $due->plus($outstanding);
                }
                if ($installment->due_date?->lt(today()) && $outstanding->isGreaterThan(BigDecimal::zero())) {
                    $overdue = $overdue->plus($outstanding);
                }
            }
        }
        return [
            'scheduled' => $this->string($scheduled), 'received' => $this->string($received),
            'outstanding' => $this->string($scheduled->minus($received)), 'due' => $this->string($due), 'overdue' => $this->string($overdue),
        ];
    }

    private function replaceDraftInstallmentsLocked(User $actor, ContractPaymentSchedule $schedule, array $lines, bool $requireExact): void
    {
        $this->assertDraft($schedule);
        $normalised = $this->normaliseLines($lines, $schedule->currency, $this->money($schedule->total_amount, 'total_amount'), $requireExact);
        $schedule->installments()->delete();
        foreach ($normalised as $index => $line) {
            ContractInstallment::withoutGlobalScopes()->create([
                'company_id' => $schedule->company_id,
                'contract_payment_schedule_id' => $schedule->id,
                'sequence' => $index + 1,
                ...$line,
            ]);
        }
    }

    private function allocateLocked(User $actor, Payment $payment, ContractPaymentSchedule $schedule, array $allocations): void
    {
        if ($allocations === []) {
            $this->invalid('allocations', __('contract_finance.errors.payment_exceeds_balance'));
        }
        $available = $this->money($payment->amount, 'amount')->minus($this->paymentAllocated($payment));
        foreach (collect($allocations)->sortBy('installment_id') as $row) {
            $amount = $this->money($row['allocated_amount'] ?? null, 'allocated_amount');
            $installment = ContractInstallment::withoutGlobalScopes()->lockForUpdate()->findOrFail($row['installment_id'] ?? 0);
            if ($installment->contract_payment_schedule_id !== $schedule->id || $installment->status !== 'scheduled') {
                $this->invalid('installment_id', __('contract_finance.errors.payment_invalid'));
            }
            if (strtoupper((string) ($row['currency'] ?? $payment->currency)) !== $schedule->currency) {
                $this->invalid('currency', __('contract_finance.errors.currency_mismatch'));
            }
            if ($amount->isGreaterThan($available)) {
                $this->invalid('allocated_amount', __('contract_finance.errors.payment_exceeds_balance'));
            }
            $net = $this->installmentNetBalance($installment);
            $limit = $payment->direction === 'incoming'
                ? $this->money($installment->amount, 'amount')->minus($net)
                : $net;
            if ($amount->isGreaterThan($limit)) {
                $this->invalid('allocated_amount', __($payment->direction === 'incoming' ? 'contract_finance.errors.installment_exceeds_balance' : 'contract_finance.errors.refund_exceeds_received'));
            }
            ContractPaymentAllocation::withoutGlobalScopes()->create([
                'company_id' => $schedule->company_id,
                'payment_id' => $payment->id,
                'contract_installment_id' => $installment->id,
                'allocated_amount' => $this->string($amount),
                'currency' => $schedule->currency,
                'allocated_by' => $actor->id,
            ]);
            $available = $available->minus($amount);
        }
        if (! $available->isZero()) {
            $this->invalid('allocations', __('contract_finance.errors.payment_exceeds_balance'));
        }
    }

    private function generatedLinesByCount(BigDecimal $total, string $currency, array $data): array
    {
        $count = filter_var($data['number_of_installments'] ?? null, FILTER_VALIDATE_INT);
        if (! $count || $count < 1) {
            $this->invalid('number_of_installments', __('contract_finance.errors.count_invalid'));
        }
        $interval = filter_var($data['interval_months'] ?? null, FILTER_VALIDATE_INT);
        if (! $interval || $interval < 1) {
            $this->invalid('interval_months', __('contract_finance.errors.interval_invalid'));
        }
        return $this->generatedLines($total, $currency, $count, $interval, $data);
    }

    private function generatedLinesByDuration(BigDecimal $total, string $currency, array $data): array
    {
        $duration = filter_var($data['duration'] ?? null, FILTER_VALIDATE_INT);
        $unit = $data['duration_unit'] ?? 'months';
        $months = $unit === 'years' ? (int) $duration * 12 : (int) $duration;
        $interval = filter_var($data['interval_months'] ?? null, FILTER_VALIDATE_INT);
        if (! $months || ! $interval || $interval < 1 || $months % $interval !== 0) {
            $this->invalid('duration', __('contract_finance.errors.duration_invalid'));
        }
        return $this->generatedLines($total, $currency, intdiv($months, $interval), $interval, $data);
    }

    private function generatedLines(BigDecimal $total, string $currency, int $count, int $interval, array $data): array
    {
        $firstDate = $this->date($data['first_installment_date'] ?? null, 'first_installment_date');
        $downPayment = filled($data['down_payment_amount'] ?? null) ? $this->money($data['down_payment_amount'], 'down_payment_amount') : BigDecimal::zero();
        if ($downPayment->isNegative() || $downPayment->isEqualTo($total)) {
            $this->invalid('down_payment_amount', __('contract_finance.errors.down_payment_invalid'));
        }
        if ($downPayment->isGreaterThan($total)) {
            $this->invalid('down_payment_amount', __('contract_finance.errors.down_payment_invalid'));
        }
        $remaining = $total->minus($downPayment);
        $base = $remaining->dividedBy($count, self::MONEY_SCALE, RoundingMode::DOWN);
        $lines = [];
        if ($downPayment->isGreaterThan(BigDecimal::zero())) {
            $lines[] = [
                'kind' => ContractInstallmentKind::DownPayment->value,
                'amount' => $this->string($downPayment), 'currency' => $currency,
                'due_date' => $this->date($data['down_payment_due_date'] ?? null, 'down_payment_due_date')->toDateString(),
            ];
        }
        $assigned = BigDecimal::zero();
        for ($index = 0; $index < $count; $index++) {
            $amount = $index === $count - 1 ? $remaining->minus($assigned) : $base;
            $assigned = $assigned->plus($amount);
            $lines[] = [
                'kind' => ContractInstallmentKind::Installment->value,
                'amount' => $this->string($amount), 'currency' => $currency,
                // addMonthsNoOverflow makes 31 Jan + 1 month deterministically 28/29 Feb.
                'due_date' => $firstDate->copy()->addMonthsNoOverflow($interval * $index)->toDateString(),
            ];
        }
        return $lines;
    }

    private function normaliseLines(array $lines, string $currency, BigDecimal $expected, bool $requireExact): array
    {
        if ($lines === []) {
            $this->invalid('installments', __('contract_finance.errors.count_invalid'));
        }
        $normalised = array_map(fn (array $line) => $this->normaliseLine($line, $currency), array_values($lines));
        if ($requireExact && ! $this->sum(array_map(fn (array $line) => $this->money($line['amount'], 'amount'), $normalised))->isEqualTo($expected)) {
            $this->invalid('installments', __('contract_finance.errors.schedule_amount_mismatch'));
        }
        return $normalised;
    }

    private function normaliseLine(array $line, string $currency): array
    {
        $amount = $this->money($line['amount'] ?? null, 'amount');
        $rawKind = $line['kind'] ?? ContractInstallmentKind::Installment;
        $kind = $rawKind instanceof ContractInstallmentKind
            ? $rawKind
            : ContractInstallmentKind::tryFrom((string) $rawKind);
        if (! $kind) {
            $this->invalid('kind', __('contract_finance.errors.payment_invalid'));
        }
        $lineCurrency = strtoupper((string) ($line['currency'] ?? $currency));
        if ($lineCurrency !== $currency) {
            $this->invalid('currency', __('contract_finance.errors.currency_mismatch'));
        }
        return [
            'kind' => $kind->value,
            'amount' => $this->string($amount),
            'currency' => $currency,
            'due_date' => $this->date($line['due_date'] ?? null, 'due_date')->toDateString(),
        ];
    }

    private function assertVersionForSchedule(Contract $contract, ContractVersion $version): void
    {
        if ($version->contract_id !== $contract->id) {
            $this->invalid('contract_version_id', __('contract_finance.errors.contract_version_mismatch'));
        }
        if (! in_array($version->status, [ContractVersionStatus::Approved, ContractVersionStatus::Signed], true)) {
            $this->invalid('contract_version_id', __('contract_finance.errors.schedule_requires_approved_version'));
        }
    }

    private function assertDraft(ContractPaymentSchedule $schedule): void
    {
        if ($schedule->status !== ContractPaymentScheduleStatus::Draft) {
            $this->invalid('schedule', __('contract_finance.errors.draft_only'));
        }
    }

    private function assertIssued(ContractPaymentSchedule $schedule): void
    {
        if ($schedule->status !== ContractPaymentScheduleStatus::Issued) {
            $this->invalid('schedule', __('contract_finance.errors.issued_only'));
        }
    }

    private function assertExactScheduleTotal(ContractPaymentSchedule $schedule): void
    {
        $sum = $this->sum($schedule->installments()->where('status', 'scheduled')->pluck('amount')->all());
        if (! $sum->isEqualTo($this->money($schedule->total_amount, 'total_amount'))) {
            $this->invalid('installments', __('contract_finance.errors.schedule_amount_mismatch'));
        }
    }

    private function contractParty(ContractPaymentSchedule $schedule, mixed $partyId): Party
    {
        $party = Party::withoutGlobalScopes()->findOrFail($partyId);
        if ($party->company_id !== $schedule->company_id || ! ContractParty::withoutGlobalScopes()
            ->where('contract_version_id', $schedule->contract_version_id)->where('party_id', $party->id)->exists()) {
            $this->invalid('party_id', __('contract_finance.errors.party_invalid'));
        }
        return $party;
    }

    private function assignedUserId(int $companyId, mixed $userId): ?int
    {
        if (! $userId) {
            return null;
        }
        $user = User::withoutGlobalScopes()->findOrFail($userId);
        if ((int) $user->company_id !== $companyId) {
            $this->invalid('assigned_to', __('contract_finance.errors.company_mismatch'));
        }
        return $user->id;
    }

    private function lockedSchedule(ContractPaymentSchedule $schedule): ContractPaymentSchedule
    {
        return ContractPaymentSchedule::withoutGlobalScopes()->with('contract')->lockForUpdate()->findOrFail($schedule->id);
    }

    private function paymentAllocated(Payment $payment): BigDecimal
    {
        return $this->sum(ContractPaymentAllocation::withoutGlobalScopes()->where('payment_id', $payment->id)->pluck('allocated_amount')->all());
    }

    private function installmentNetBalance(ContractInstallment $installment): BigDecimal
    {
        $rows = ContractPaymentAllocation::withoutGlobalScopes()->where('contract_installment_id', $installment->id)
            ->with('payment:id,direction,status')->get();
        return $rows->reduce(function (BigDecimal $total, ContractPaymentAllocation $allocation): BigDecimal {
            if ($allocation->payment?->status !== 'completed') {
                return $total;
            }
            $amount = $this->money($allocation->allocated_amount, 'allocated_amount');
            return $allocation->payment->direction === 'incoming' ? $total->plus($amount) : $total->minus($amount);
        }, BigDecimal::zero());
    }

    private function event(User $actor, Contract $contract, string $type, string $key, ?ContractPaymentSchedule $schedule = null, ?Payment $payment = null, array $payload = []): ContractFinancialEvent
    {
        return ContractFinancialEvent::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $contract->company_id,
            'idempotency_key' => $key,
        ], [
            'contract_id' => $contract->id,
            'contract_payment_schedule_id' => $schedule?->id,
            'payment_id' => $payment?->id,
            'event_type' => $type,
            'payload' => $payload,
            'actor_id' => $actor->id,
            'occurred_at' => now(),
        ]);
    }

    private function isSettled(Contract $contract): bool
    {
        $summary = $this->summary($contract);
        return BigDecimal::of($summary['scheduled'])->isGreaterThan(BigDecimal::zero())
            && BigDecimal::of($summary['outstanding'])->isZero();
    }

    private function money(mixed $value, string $field): BigDecimal
    {
        try {
            $amount = BigDecimal::of((string) $value)->toScale(self::MONEY_SCALE, RoundingMode::UNNECESSARY);
        } catch (\Throwable) {
            $this->invalid($field, __('contract_finance.errors.positive_amount'));
        }
        if (! $amount->isGreaterThan(BigDecimal::zero())) {
            $this->invalid($field, __('contract_finance.errors.positive_amount'));
        }
        return $amount;
    }

    /** @param iterable<BigDecimal|string|int|float> $amounts */
    private function sum(iterable $amounts): BigDecimal
    {
        $total = BigDecimal::zero();
        foreach ($amounts as $amount) {
            $total = $total->plus(BigDecimal::of((string) $amount));
        }
        return $total->toScale(self::MONEY_SCALE, RoundingMode::UNNECESSARY);
    }

    private function string(BigDecimal $amount): string
    {
        return (string) $amount->toScale(self::MONEY_SCALE, RoundingMode::UNNECESSARY);
    }

    private function date(mixed $value, string $field): Carbon
    {
        try {
            if (blank($value)) {
                throw new \InvalidArgumentException;
            }
            return $value instanceof Carbon ? $value->copy()->startOfDay() : Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            $this->invalid($field, __('contract_finance.errors.invalid_due_date'));
        }
    }

    private function sameCompany(User $actor, int $companyId): void
    {
        if (! $actor->isSuperAdmin() && (int) $actor->company_id !== $companyId) {
            throw new AuthorizationException;
        }
    }

    private function can(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException;
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
