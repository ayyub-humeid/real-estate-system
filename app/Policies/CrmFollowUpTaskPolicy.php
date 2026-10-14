<?php

namespace App\Policies;

use App\Models\{CrmFollowUpTask, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmFollowUpTaskPolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;
    public function viewAny(User $user): bool { return $user->can('view_any_crm::follow_up_task') || $user->can('view_own_crm_follow_up_tasks') || $user->can('view_team_crm_follow_up_tasks'); }
    public function view(User $user, CrmFollowUpTask $record): bool { return $this->canForRecord($user, 'view_crm::follow_up_task', $record) || ($record->assigned_to === $user->id && $user->can('view_own_crm_follow_up_tasks')) || $this->canForRecord($user, 'view_team_crm_follow_up_tasks', $record); }
    public function create(User $user): bool { return $user->can('create_crm::follow_up_task') || $user->can('create_crm_follow_up_task'); }
    public function update(User $user, CrmFollowUpTask $record): bool { return $this->canForRecord($user, 'update_crm::follow_up_task', $record) || $this->canForRecord($user, 'update_crm_follow_up_task', $record); }
    public function delete(User $user, CrmFollowUpTask $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restore(User $user, CrmFollowUpTask $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, CrmFollowUpTask $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function replicate(User $user, CrmFollowUpTask $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
    public function assign(User $user, CrmFollowUpTask $record): bool { return $this->canForRecord($user, 'assign_crm_follow_up_task', $record); }
    public function complete(User $user, CrmFollowUpTask $record): bool { return $this->canForRecord($user, 'complete_crm_follow_up_task', $record) && ($record->assigned_to === $user->id || $this->canForRecord($user, 'complete_any_crm_follow_up_task', $record)); }
    public function cancel(User $user, CrmFollowUpTask $record): bool { return $this->canForRecord($user, 'cancel_crm_follow_up_task', $record); }
}
