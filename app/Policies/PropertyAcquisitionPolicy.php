<?php

namespace App\Policies;

use App\Models\PropertyAcquisition;
use App\Models\User;
use App\Policies\Concerns\CompanyOwnedPolicy;

use Illuminate\Auth\Access\HandlesAuthorization;

class PropertyAcquisitionPolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_property::acquisition');
    }
    public function view(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'view_property::acquisition', $acquisition);
    }
    public function create(User $user): bool
    {
        return $user->can('create_property::acquisition');
    }
    public function update(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'update_property::acquisition', $acquisition);
    }
    public function delete(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'delete_property::acquisition', $acquisition)
            && ! in_array($acquisition->status, ['completed', 'cancelled'], true);
    }
    public function deleteAny(User $user): bool
    {
        // Bulk deletion cannot safely enforce the per-record history rule.
        return false;
    }
    public function forceDelete(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'force_delete_property::acquisition', $acquisition);
    }
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_property::acquisition');
    }
    public function restore(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'restore_property::acquisition', $acquisition);
    }
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_property::acquisition');
    }
    public function replicate(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'replicate_property::acquisition', $acquisition);
    }
    public function reorder(User $user): bool
    {
        return $user->can('reorder_property::acquisition');
    }
    public function approve(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'approve_property_acquisition', $acquisition);
    }
    public function cancel(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'cancel_property_acquisition', $acquisition);
    }
    public function complete(User $user, PropertyAcquisition $acquisition): bool
    {
        return $this->canForRecord($user, 'complete_property_acquisition', $acquisition);
    }
}
