<?php

namespace App\Services;

use App\Enums\ContractPaymentScheduleStatus;
use App\Models\{CompanySetting, ContractFinancialEvent, ContractInstallment};
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class ContractFinancialReminderService
{
    public function send(): int
    {
        $count = 0;
        $installments = ContractInstallment::withoutGlobalScopes()
            ->with(['schedule.contract', 'schedule.contractVersion'])
            ->where('status', 'scheduled')
            ->whereHas('schedule', fn ($query) => $query->where('status', ContractPaymentScheduleStatus::Issued->value))
            ->orderBy('id')
            ->get();

        foreach ($installments as $installment) {
            $setting = CompanySetting::withoutGlobalScopes()->where('company_id', $installment->company_id)->first();
            $leadDays = $setting?->contract_payment_reminder_days ?? 3;
            $type = match (true) {
                $installment->due_date?->isToday() => 'installment_due',
                $installment->due_date?->isPast() => 'installment_overdue',
                $installment->due_date?->isSameDay(today()->addDays($leadDays)) => 'installment_upcoming',
                default => null,
            };
            if (! $type || ! $this->hasOutstandingBalance($installment)) {
                continue;
            }

            DB::transaction(function () use ($installment, $type, &$count): void {
                $locked = ContractInstallment::withoutGlobalScopes()->lockForUpdate()->findOrFail($installment->id);
                if (! $this->hasOutstandingBalance($locked)) {
                    return;
                }
                $event = ContractFinancialEvent::withoutGlobalScopes()->firstOrCreate([
                    'company_id' => $locked->company_id,
                    'idempotency_key' => 'installment:'.$locked->id.':'.$type,
                ], [
                    'contract_id' => $locked->schedule->contract_id,
                    'contract_payment_schedule_id' => $locked->contract_payment_schedule_id,
                    'contract_installment_id' => $locked->id,
                    'event_type' => $type,
                    'payload' => ['due_date' => $locked->due_date?->toDateString(), 'amount' => $locked->amount, 'currency' => $locked->currency],
                    'occurred_at' => now(),
                ]);
                if ($event->wasRecentlyCreated) {
                    $count++;
                    DB::afterCommit(fn () => app(ContractFinancialNotificationDispatcher::class)->dispatchForEvent($event->id));
                }
            });
        }

        return $count;
    }

    private function hasOutstandingBalance(ContractInstallment $installment): bool
    {
        $net = BigDecimal::zero();
        foreach ($installment->allocations()->with('payment:id,direction,status')->get() as $allocation) {
            if ($allocation->payment?->status !== 'completed') {
                continue;
            }
            $amount = BigDecimal::of((string) $allocation->allocated_amount);
            $net = $allocation->payment->direction === 'incoming' ? $net->plus($amount) : $net->minus($amount);
        }
        return BigDecimal::of((string) $installment->amount)->isGreaterThan($net);
    }
}
