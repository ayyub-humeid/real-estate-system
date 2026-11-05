<?php

namespace App\Policies;

use App\Models\{ContractPaymentAllocation, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class ContractPaymentAllocationPolicy
{
    use CompanyOwnedPolicy;

    public function viewAny(User $user): bool { return $this->canForResource($user, 'view_contract_financials'); }
    public function view(User $user, ContractPaymentAllocation $allocation): bool { return $this->canForRecord($user, 'view_contract_financials', $allocation); }
    public function create(User $user): bool { return $this->canForResource($user, 'allocate_contract_payment'); }
    public function update(User $user, ContractPaymentAllocation $allocation): bool { return false; }
    public function delete(User $user, ContractPaymentAllocation $allocation): bool { return false; }
}
