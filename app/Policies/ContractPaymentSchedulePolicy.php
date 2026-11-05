<?php

namespace App\Policies;

use App\Enums\ContractPaymentScheduleStatus;
use App\Models\{ContractPaymentSchedule, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class ContractPaymentSchedulePolicy
{
    use CompanyOwnedPolicy;

    public function viewAny(User $user): bool { return $this->canForResource($user, 'view_contract_financials'); }
    public function view(User $user, ContractPaymentSchedule $schedule): bool { return $this->canForRecord($user, 'view_contract_financials', $schedule); }
    public function create(User $user): bool { return $this->canForResource($user, 'create_contract_payment_schedule'); }
    public function update(User $user, ContractPaymentSchedule $schedule): bool { return $schedule->status === ContractPaymentScheduleStatus::Draft && $this->canForRecord($user, 'update_contract_payment_schedule', $schedule); }
    public function delete(User $user, ContractPaymentSchedule $schedule): bool { return false; }
    public function submit(User $user, ContractPaymentSchedule $schedule): bool { return $schedule->status === ContractPaymentScheduleStatus::Draft && $this->canForRecord($user, 'submit_contract_payment_schedule', $schedule); }
    public function issue(User $user, ContractPaymentSchedule $schedule): bool { return $schedule->status === ContractPaymentScheduleStatus::PendingApproval && $this->canForRecord($user, 'approve_contract_payment_schedule', $schedule); }
    public function reschedule(User $user, ContractPaymentSchedule $schedule): bool { return $schedule->status === ContractPaymentScheduleStatus::Issued && $this->canForRecord($user, 'reschedule_contract_payment_schedule', $schedule); }
    public function cancel(User $user, ContractPaymentSchedule $schedule): bool { return $this->canForRecord($user, 'cancel_contract_payment_schedule', $schedule); }
}
