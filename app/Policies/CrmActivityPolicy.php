<?php

namespace App\Policies;

use App\Models\{CrmActivity, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmActivityPolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_crm::activity') || $user->can('view_crm_activity');
    }
    public function view(User $user, CrmActivity $record): bool
    {
        return $this->canForRecord($user, 'view_crm::activity', $record) || $this->canForRecord($user, 'view_crm_activity', $record);
    }
    public function create(User $user): bool
    {
        return $user->can('create_crm::activity') || $user->can('create_crm_activity');
    }
    public function update(User $user, CrmActivity $record): bool
    {
        return false;
    }
    public function delete(User $user, CrmActivity $record): bool
    {
        return false;
    }
    public function deleteAny(User $user): bool
    {
        return false;
    }
    public function restore(User $user, CrmActivity $record): bool
    {
        return false;
    }
    public function restoreAny(User $user): bool
    {
        return false;
    }
    public function forceDelete(User $user, CrmActivity $record): bool
    {
        return false;
    }
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
    public function replicate(User $user, CrmActivity $record): bool
    {
        return false;
    }
    public function reorder(User $user): bool
    {
        return false;
    }
}
