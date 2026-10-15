<?php

namespace App\Policies;

use App\Enums\{OfferRevisionStatus, OfferStatus};
use App\Models\{CrmOffer, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmOfferPolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;
    public function viewAny(User $user): bool { return $user->can('view_any_crm::offer') || $user->can('view_own_crm_offer'); }
    public function view(User $user, CrmOffer $record): bool { return $this->canForRecord($user, 'view_crm::offer', $record) || $this->ownsOffer($user, $record, 'view_own_crm_offer'); }
    public function create(User $user): bool { return $user->can('create_crm::offer'); }
    public function update(User $user, CrmOffer $record): bool { return $this->canForRecord($user, 'update_crm::offer', $record) || $this->ownsOffer($user, $record, 'manage_own_crm_offer'); }
    public function delete(User $user, CrmOffer $record): bool { return $this->canForRecord($user, 'delete_crm::offer', $record) && $record->status === OfferStatus::Draft && ! $record->revisions()->where('status', '!=', OfferRevisionStatus::Draft->value)->exists(); }
    public function deleteAny(User $user): bool { return $user->can('delete_any_crm::offer'); }
    public function restore(User $user, CrmOffer $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, CrmOffer $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function replicate(User $user, CrmOffer $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
    public function createRevision(User $user, CrmOffer $record): bool { return $this->canForRecord($user, 'create_offer_revision', $record) || $this->ownsOffer($user, $record, 'manage_own_crm_offer'); }
    public function assign(User $user, CrmOffer $record): bool { return $this->canForRecord($user, 'assign_offer', $record); }
    public function withdraw(User $user, CrmOffer $record): bool { return $this->canForRecord($user, 'withdraw_offer', $record) || $this->ownsOffer($user, $record, 'manage_own_crm_offer'); }
    public function close(User $user, CrmOffer $record): bool { return $this->canForRecord($user, 'close_offer', $record); }
    public function expire(User $user, CrmOffer $record): bool { return $this->canForRecord($user, 'expire_offer', $record); }
    private function ownsOffer(User $user, CrmOffer $record, string $permission): bool { return $this->belongsToUserCompany($user, $record) && (int) $record->assigned_to === (int) $user->id && $user->can($permission); }
}
