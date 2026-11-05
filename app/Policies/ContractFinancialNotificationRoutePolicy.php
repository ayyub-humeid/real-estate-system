<?php

namespace App\Policies;

use App\Models\{ContractFinancialNotificationRoute, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class ContractFinancialNotificationRoutePolicy
{
    use CompanyOwnedPolicy;

    public function viewAny(User $user): bool { return $this->canForResource($user, 'manage_contract_financial_notification_routes'); }
    public function view(User $user, ContractFinancialNotificationRoute $route): bool { return $this->canForRecord($user, 'manage_contract_financial_notification_routes', $route); }
    public function create(User $user): bool { return $this->canForResource($user, 'manage_contract_financial_notification_routes'); }
    public function update(User $user, ContractFinancialNotificationRoute $route): bool { return $this->canForRecord($user, 'manage_contract_financial_notification_routes', $route); }
    public function delete(User $user, ContractFinancialNotificationRoute $route): bool { return $this->canForRecord($user, 'manage_contract_financial_notification_routes', $route); }
}
