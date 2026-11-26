<?php

namespace App\Services;

use App\Enums\ContractPaymentScheduleStatus;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\ContractVersionStatus;
use App\Enums\PaymentStatus;
use App\Models\CompanySetting;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The rent roll: every active rental with what it is owed, in one query.
 *
 * The figures must agree with ContractFinancialService::summary(), which is the
 * authority for a single contract. Both read issued and superseded schedules,
 * skip cancelled and replaced installments, and count only completed payments,
 * with a refund (an outgoing payment) reducing what was received.
 */
class RentRollQuery
{
    public function __construct(private readonly User $actor) {}

    /**
     * Days a company allows after the due date before rent counts as late.
     * A super admin sees every company at once, so exact due dates are used.
     */
    public function graceDays(): int
    {
        if ($this->actor->isSuperAdmin() || ! $this->actor->company_id) {
            return 0;
        }

        return (int) (CompanySetting::withoutGlobalScopes()
            ->where('company_id', $this->actor->company_id)
            ->value('payment_grace_period_days') ?? 0);
    }

    /** The last day rent can arrive without being late. */
    public function overdueBefore(): Carbon
    {
        return today()->subDays($this->graceDays());
    }

    /**
     * Active lease contracts, each carrying its rent terms and money position.
     * The company scope on Contract keeps one company's rent roll to itself.
     */
    public function builder(): Builder
    {
        $today = today()->toDateString();
        $overdueBefore = $this->overdueBefore()->toDateString();
        $signed = [ContractVersionStatus::Signed->value, ContractVersionStatus::Approved->value];

        return Contract::query()
            ->where('contracts.status', ContractStatus::Active->value)
            ->where('contracts.contract_type', ContractType::Lease->value)
            ->with(['unit:id,unit_number,property_id', 'unit.property:id,name', 'company:id,name'])
            ->leftJoinSub($this->contractBalances($today, $overdueBefore), 'roll', 'roll.contract_id', '=', 'contracts.id')
            ->select('contracts.*')
            ->selectRaw('COALESCE(roll.outstanding_amount, 0) as outstanding_amount')
            ->selectRaw('COALESCE(roll.overdue_amount, 0) as overdue_amount')
            ->selectRaw('COALESCE(roll.installment_count, 0) as installment_count')
            ->selectRaw('roll.next_due_date as next_due_date')
            ->selectRaw('roll.oldest_overdue_date as oldest_overdue_date')
            ->addSelect([
                'rent_amount' => $this->fromLatestVersion('rent_amount', $signed),
                'rent_frequency' => $this->fromLatestVersion('rent_frequency', $signed),
                'contract_currency' => $this->fromLatestVersion('currency', $signed),
                'term_end_date' => $this->fromLatestVersion('end_date', $signed),
                'tenant_name' => $this->tenantName(),
            ]);
    }

    /** One row per contract: what is still owed, what is late, and when the next payment is due. */
    private function contractBalances(string $today, string $overdueBefore): \Illuminate\Database\Query\Builder
    {
        return DB::query()
            ->fromSub($this->installmentBalances(), 'balance')
            ->groupBy('balance.contract_id')
            ->select('balance.contract_id')
            ->selectRaw('SUM(CASE WHEN balance.outstanding > 0 THEN balance.outstanding ELSE 0 END) as outstanding_amount')
            ->selectRaw('SUM(CASE WHEN balance.outstanding > 0 AND balance.due_date < ? THEN balance.outstanding ELSE 0 END) as overdue_amount', [$overdueBefore])
            ->selectRaw('MIN(CASE WHEN balance.outstanding > 0 AND balance.due_date >= ? THEN balance.due_date END) as next_due_date', [$today])
            ->selectRaw('MIN(CASE WHEN balance.outstanding > 0 AND balance.due_date < ? THEN balance.due_date END) as oldest_overdue_date', [$overdueBefore])
            ->selectRaw('COUNT(*) as installment_count');
    }

    /** One row per payable installment with the balance still outstanding on it. */
    private function installmentBalances(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('contract_installments as ci')
            ->join('contract_payment_schedules as cps', 'cps.id', '=', 'ci.contract_payment_schedule_id')
            ->leftJoin('contract_payment_allocations as cpa', 'cpa.contract_installment_id', '=', 'ci.id')
            ->leftJoin('payments as p', fn ($join) => $join
                ->on('p.id', '=', 'cpa.payment_id')
                ->where('p.status', '=', PaymentStatus::Completed->value))
            ->whereIn('cps.status', [ContractPaymentScheduleStatus::Issued->value, ContractPaymentScheduleStatus::Superseded->value])
            ->where('ci.status', 'scheduled')
            ->groupBy('cps.contract_id', 'ci.id', 'ci.due_date', 'ci.amount')
            ->select('cps.contract_id', 'ci.due_date')
            // A refund is an outgoing payment, so it puts the balance back.
            ->selectRaw('ci.amount - COALESCE(SUM(CASE WHEN p.id IS NULL THEN 0 WHEN p.direction = ? THEN cpa.allocated_amount ELSE -cpa.allocated_amount END), 0) as outstanding', ['incoming']);

        // Defence in depth and a narrower index scan: a company user's rent roll
        // never aggregates another company's installments.
        return $this->actor->isSuperAdmin() || ! $this->actor->company_id
            ? $query
            : $query->where('ci.company_id', $this->actor->company_id);
    }

    /** A column of the contract's most recent legally effective version. */
    private function fromLatestVersion(string $column, array $statuses): \Illuminate\Database\Query\Builder
    {
        return DB::table('contract_versions')
            ->select($column)
            ->whereColumn('contract_versions.contract_id', 'contracts.id')
            ->whereIn('contract_versions.status', $statuses)
            ->orderByDesc('contract_versions.version_number')
            ->limit(1);
    }

    private function tenantName(): \Illuminate\Database\Query\Builder
    {
        return DB::table('parties')
            ->select('parties.name')
            ->join('contract_parties as cp', 'cp.party_id', '=', 'parties.id')
            ->join('contract_versions as cv', 'cv.id', '=', 'cp.contract_version_id')
            ->whereColumn('cv.contract_id', 'contracts.id')
            ->where('cp.role', 'tenant')
            ->orderByDesc('cv.version_number')
            ->limit(1);
    }
}
