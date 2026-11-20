<?php

namespace App\Services;

use App\Enums\{ContractStatus, ContractType, ContractVersionStatus};
use App\Models\{Contract, User};
use App\Notifications\ContractLeaseOverdueNotification;

/**
 * Finds active lease contracts whose signed term ended before today and tells
 * the creator and the people allowed to end contracts. It never ends anything.
 * Each person is told once per contract.
 */
class LeaseExpiryAlertService
{
    /** @return int number of notifications sent */
    public function notifyOverdue(): int
    {
        $sent = 0;

        Contract::withoutGlobalScopes()
            ->where('status', ContractStatus::Active->value)
            ->where('contract_type', ContractType::Lease->value)
            ->with(['unit' => fn ($query) => $query->withoutGlobalScopes(), 'versions' => fn ($query) => $query->withoutGlobalScopes()->where('status', ContractVersionStatus::Signed->value)->orderByDesc('version_number')])
            ->orderBy('id')
            ->chunkById(100, function ($contracts) use (&$sent): void {
                foreach ($contracts as $contract) {
                    $end = $contract->versions->first()?->end_date;
                    if (! $end || ! $end->copy()->endOfDay()->isPast()) {
                        continue;
                    }

                    $notification = new ContractLeaseOverdueNotification($contract->id, $contract->contract_number, (string) ($contract->unit?->unit_number ?? '—'), $end->format('d/m/Y'));
                    foreach ($this->recipients($contract) as $user) {
                        $already = $user->notifications()
                            ->where('type', ContractLeaseOverdueNotification::class)
                            ->get(['id', 'data'])
                            ->contains(fn ($notification): bool => (int) ($notification->data['contract_id'] ?? 0) === $contract->id);
                        if (! $already) {
                            $user->notify($notification);
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }

    private function recipients(Contract $contract)
    {
        // The permission is seeded, but never let a missing row crash the daily command.
        $permissionExists = \Spatie\Permission\Models\Permission::query()->where('name', 'end_contract')->where('guard_name', 'web')->exists();
        $managers = $permissionExists
            ? User::withoutGlobalScopes()->where('company_id', $contract->company_id)->permission('end_contract')->get()
            : collect();
        $creator = $contract->created_by ? User::withoutGlobalScopes()->find($contract->created_by) : null;

        return $managers->when($creator, fn ($users) => $users->push($creator))->unique('id');
    }
}
