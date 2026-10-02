<?php
namespace App\Policies\Concerns;
use App\Models\User;
trait CompanyOwnedPolicy {
    protected function canForResource(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    protected function belongsToUserCompany(User $user, object $record): bool { return $user->isSuperAdmin() || (int) $user->company_id === (int) $record->company_id; }
    protected function canForRecord(User $user, string $permission, object $record): bool
    {
        return $this->belongsToUserCompany($user, $record) && $user->can($permission);
    }
}
