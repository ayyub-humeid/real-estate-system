<?php

namespace App\Policies;

use App\Enums\ContractPaymentScheduleStatus;
use App\Models\{ContractInstallment, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class ContractInstallmentPolicy
{
    use CompanyOwnedPolicy;

    public function viewAny(User $user): bool { return $this->canForResource($user, 'view_contract_financials'); }
    public function view(User $user, ContractInstallment $installment): bool { return $this->canForRecord($user, 'view_contract_financials', $installment); }
    public function create(User $user): bool { return $this->canForResource($user, 'update_contract_payment_schedule'); }
    public function update(User $user, ContractInstallment $installment): bool { return $installment->schedule?->status === ContractPaymentScheduleStatus::Draft && $this->canForRecord($user, 'update_contract_payment_schedule', $installment); }
    public function delete(User $user, ContractInstallment $installment): bool { return $this->update($user, $installment); }
}
