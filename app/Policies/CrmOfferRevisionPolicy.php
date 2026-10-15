<?php

namespace App\Policies;

use App\Models\{CrmOfferRevision, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmOfferRevisionPolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;
    public function viewAny(User $user): bool { return $user->can('view_any_offer_revision') || $user->can('view_own_crm_offer'); }
    public function view(User $user, CrmOfferRevision $record): bool { return $this->canForRecord($user, 'view_offer_revision', $record) || $this->ownsOffer($user, $record, 'view_own_crm_offer'); }
    public function create(User $user): bool { return $user->can('create_offer_revision') || $user->can('manage_own_crm_offer'); }
    // The service owns the draft-only rule so callers receive a clear domain
    // validation message when they try to change submitted history.
    public function update(User $user, CrmOfferRevision $record): bool { return $this->canForRecord($user, 'update_offer_revision', $record) || $this->ownsOffer($user, $record, 'manage_own_crm_offer'); }
    public function delete(User $user, CrmOfferRevision $record): bool { return $this->canForRecord($user, 'delete_offer_revision', $record) && $record->status->value === 'draft'; }
    public function deleteAny(User $user): bool { return false; }
    public function restore(User $user, CrmOfferRevision $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, CrmOfferRevision $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function replicate(User $user, CrmOfferRevision $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
    public function submit(User $user, CrmOfferRevision $record): bool { return $this->canForRecord($user, 'submit_offer_revision', $record) || $this->ownsOffer($user, $record, 'manage_own_crm_offer'); }
    public function requestApproval(User $user, CrmOfferRevision $record): bool { return $this->canForRecord($user, 'request_offer_approval', $record) || $this->ownsOffer($user, $record, 'manage_own_crm_offer'); }
    public function approveRevision(User $user, CrmOfferRevision $record): bool { return $this->canForRecord($user, 'approve_offer_revision', $record); }
    public function rejectRevision(User $user, CrmOfferRevision $record): bool { return $this->canForRecord($user, 'reject_offer_revision', $record); }
    public function confirmAgreement(User $user, CrmOfferRevision $record): bool { return $this->canForRecord($user, 'confirm_offer_agreement', $record); }
    private function ownsOffer(User $user, CrmOfferRevision $record, string $permission): bool { return $this->belongsToUserCompany($user, $record) && (int) $record->offer?->assigned_to === (int) $user->id && $user->can($permission); }
}
