<?php

namespace App\Policies;

use App\Models\{UnitReservation, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitReservationPolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;
    public function viewAny(User $user): bool { return $user->can('view_any_unit::reservation'); }
    public function view(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'view_unit::reservation', $record); }
    public function create(User $user): bool { return $user->can('create_unit::reservation'); }
    public function update(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'update_unit::reservation', $record) && $record->status->value === 'draft'; }
    public function delete(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'delete_unit::reservation', $record) && $record->status->value === 'draft'; }
    public function deleteAny(User $user): bool { return $user->can('delete_any_unit::reservation'); }
    public function restore(User $user, UnitReservation $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, UnitReservation $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function replicate(User $user, UnitReservation $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
    public function sendForApproval(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'send_reservation_for_approval', $record); }
    public function approveAndActivate(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'approve_and_activate_reservation', $record); }
    public function rejectRequest(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'reject_reservation_request', $record); }
    public function cancelReservation(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'cancel_unit_reservation', $record); }
    public function extendReservation(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'extend_unit_reservation', $record); }
    public function expireReservation(User $user, UnitReservation $record): bool { return $this->canForRecord($user, 'expire_unit_reservation', $record); }
}
