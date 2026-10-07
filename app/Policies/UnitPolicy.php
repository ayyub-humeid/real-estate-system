<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Unit;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_unit');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Unit $unit): bool
    {
        return $this->belongs($user, $unit) && $user->can('view_unit');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Unit $unit = null): bool
    {
        return $user->can('create_unit') && (! $unit || $this->belongs($user, $unit));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Unit $unit): bool
    {
        return $this->belongs($user, $unit) && $user->can('update_unit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $this->belongs($user, $unit) && $user->can('delete_unit') && $unit->status === 'draft' && ! $unit->documents()->exists() && ! $unit->ownerships()->exists() && ! $unit->statusHistories()->exists() && ! $unit->leases()->exists() && ! $unit->maintenanceRequests()->exists() && ! $unit->ratings()->exists();
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_unit');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Unit $unit): bool
    {
        return $user->can('force_delete_unit');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_unit');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Unit $unit): bool
    {
        return $user->can('restore_unit');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_unit');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Unit $unit): bool
    {
        return $user->can('replicate_unit');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_unit');
    }

    public function changeStatus(User $user, Unit $unit): bool { return $this->belongs($user, $unit) && $user->can('change_unit_status'); }
    public function reactivate(User $user, Unit $unit): bool { return $this->belongs($user, $unit) && $user->can('reactivate_unit'); }
    public function changeOwnership(User $user, Unit $unit): bool { return $this->belongs($user, $unit) && $user->can('change_unit_ownership'); }
    public function manageDocuments(User $user, Unit $unit): bool { return $this->belongs($user, $unit) && $user->can('manage_unit_documents'); }
    private function belongs(User $user, Unit $unit): bool { return $user->isSuperAdmin() || (int) $user->company_id === (int) $unit->company_id; }
}
