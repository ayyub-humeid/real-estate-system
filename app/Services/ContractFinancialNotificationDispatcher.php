<?php

namespace App\Services;

use App\Models\{Company, ContractFinancialEvent, ContractFinancialNotificationDelivery, ContractFinancialNotificationRoute, ContractPaymentSchedule, Payment, User};
use App\Notifications\ContractFinancialNotification;
use Illuminate\Support\Facades\DB;

class ContractFinancialNotificationDispatcher
{
    public function dispatchForSchedule(int $scheduleId, string $eventType): void
    {
        $event = ContractFinancialEvent::withoutGlobalScopes()
            ->where('contract_payment_schedule_id', $scheduleId)
            ->where('event_type', $eventType)
            ->latest('id')
            ->first();
        if ($event) {
            $this->deliver($event->id);
        }
    }

    public function dispatchForPayment(int $paymentId, string $eventType): void
    {
        $event = ContractFinancialEvent::withoutGlobalScopes()
            ->where('payment_id', $paymentId)
            ->where('event_type', $eventType)
            ->latest('id')
            ->first();
        if ($event) {
            $this->deliver($event->id);
        }
    }

    public function dispatchForEvent(int $eventId): void
    {
        $this->deliver($eventId);
    }

    public function deliver(int $eventId): void
    {
        $event = ContractFinancialEvent::withoutGlobalScopes()
            ->with(['contract', 'schedule.assignedTo'])
            ->findOrFail($eventId);
        $permission = $this->requiredPermission($event->event_type);
        $recipients = $this->recipients($event, $permission);

        foreach ($recipients as $recipient) {
            DB::transaction(function () use ($event, $recipient): void {
                $delivery = ContractFinancialNotificationDelivery::query()->firstOrCreate([
                    'contract_financial_event_id' => $event->id,
                    'user_id' => $recipient->id,
                    'channel' => 'database',
                ]);
                if ($delivery->delivered_at) {
                    return;
                }
                $recipient->notify(new ContractFinancialNotification($event));
                $delivery->update(['delivered_at' => now()]);
            });
        }
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function recipients(ContractFinancialEvent $event, string $permission): \Illuminate\Support\Collection
    {
        $company = Company::withoutGlobalScopes()->find($event->company_id);
        if (! $company || ! $company->is_active) {
            return collect();
        }
        $recipients = collect();
        $assigned = $event->schedule?->assignedTo;
        if ($assigned && $this->eligible($assigned, $company->id, $permission)) {
            $recipients->push($assigned);
        }

        $primary = $this->routedUsers($event->company_id, $event->event_type, false, $permission);
        $recipients = $recipients->merge($primary)->unique('id')->values();
        if ($recipients->isEmpty()) {
            $recipients = $this->routedUsers($event->company_id, $event->event_type, true, $permission)->unique('id')->values();
        }

        return $recipients;
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function routedUsers(int $companyId, string $eventType, bool $fallback, string $permission): \Illuminate\Support\Collection
    {
        return ContractFinancialNotificationRoute::withoutGlobalScopes()
            ->with('user')
            ->where('company_id', $companyId)
            ->where('event_type', $eventType)
            ->where('is_fallback', $fallback)
            ->get()
            ->pluck('user')
            ->filter(fn (?User $user): bool => $user && $this->eligible($user, $companyId, $permission))
            ->values();
    }

    private function eligible(User $user, int $companyId, string $permission): bool
    {
        return (int) $user->company_id === $companyId && $user->can($permission);
    }

    private function requiredPermission(string $eventType): string
    {
        return match ($eventType) {
            'schedule_submitted' => 'approve_contract_payment_schedule',
            'schedule_issued', 'schedule_rescheduled', 'schedule_cancelled' => 'view_contract_financials',
            default => 'view_contract_financials',
        };
    }
}
