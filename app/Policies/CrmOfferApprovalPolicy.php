<?php

namespace App\Policies;

use App\Models\{CrmOfferApproval, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmOfferApprovalPolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;
    public function viewAny(User $user): bool { return $user->can('view_any_offer_approval') || $user->can('view_own_crm_offer'); }
    public function view(User $user, CrmOfferApproval $record): bool { return $this->canForRecord($user, 'view_offer_approval', $record) || ($this->belongsToUserCompany($user, $record) && (int) $record->revision?->offer?->assigned_to === (int) $user->id && $user->can('view_own_crm_offer')); }
    public function create(User $user): bool { return false; }
    public function update(User $user, CrmOfferApproval $record): bool { return false; }
    public function delete(User $user, CrmOfferApproval $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restore(User $user, CrmOfferApproval $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, CrmOfferApproval $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function replicate(User $user, CrmOfferApproval $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}
