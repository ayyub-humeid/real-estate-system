<?php

namespace App\Policies;

use App\Models\PropertyAcquisition;
use App\Models\User;

use Illuminate\Auth\Access\HandlesAuthorization;

class PropertyAcquisitionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_property::acquisition');
    }
    public function view(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('view_property::acquisition');
    }
    public function create(User $user): bool
    {
        return $user->can('create_property::acquisition');
    }
    public function update(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('update_property::acquisition');
    }
    public function delete(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('delete_property::acquisition')
            && ! in_array($acquisition->status, ['completed', 'cancelled'], true);
    }
    public function deleteAny(User $user): bool
    {
        // Bulk deletion cannot safely enforce the per-record history rule.
        return false;
    }
    public function forceDelete(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('force_delete_property::acquisition');
    }
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_property::acquisition');
    }
    public function restore(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('restore_property::acquisition');
    }
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_property::acquisition');
    }
    public function replicate(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('replicate_property::acquisition');
    }
    public function reorder(User $user): bool
    {
        return $user->can('reorder_property::acquisition');
    }
    public function approve(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('approve_property_acquisition');
    }
    public function cancel(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('cancel_property_acquisition');
    }
    public function complete(User $user, PropertyAcquisition $acquisition): bool
    {
        return $user->can('complete_property_acquisition');
    }
}
