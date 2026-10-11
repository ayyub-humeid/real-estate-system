<?php

namespace App\Policies;

use App\Enums\ListingStatus;
use App\Models\{Listing, User};
use App\Policies\Concerns\CompanyOwnedPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class ListingPolicy
{
    use HandlesAuthorization, CompanyOwnedPolicy;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_listing');
    }
    public function view(User $user, Listing $listing): bool
    {
        return $this->canForRecord($user, 'view_listing', $listing);
    }
    public function create(User $user): bool
    {
        return $user->can('create_listing');
    }
    public function update(User $user, Listing $listing): bool
    {
        return $listing->status !== ListingStatus::Archived && $this->canForRecord($user, 'update_listing', $listing);
    }
    public function delete(User $user, Listing $listing): bool
    {
        return $listing->status === ListingStatus::Draft && $this->canForRecord($user, 'delete_listing', $listing);
    }
    public function deleteAny(User $user): bool
    {
        return false;
    }
    public function forceDelete(User $user, Listing $listing): bool
    {
        return false;
    }
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
    public function restore(User $user, Listing $listing): bool
    {
        return $listing->status === ListingStatus::Archived && $this->canForRecord($user, 'restore_listing', $listing);
    }
    public function restoreAny(User $user): bool
    {
        return false;
    }
    public function replicate(User $user, Listing $listing): bool
    {
        return false;
    }
    public function reorder(User $user): bool
    {
        return false;
    }
    public function publish(User $user, Listing $listing): bool
    {
        return $this->canForRecord($user, 'publish_listing', $listing);
    }
    public function pause(User $user, Listing $listing): bool
    {
        return $this->canForRecord($user, 'pause_listing', $listing);
    }
    public function archive(User $user, Listing $listing): bool
    {
        return $this->canForRecord($user, 'archive_listing', $listing);
    }
    public function changePrice(User $user, Listing $listing): bool
    {
        return $this->canForRecord($user, 'change_listing_price', $listing);
    }
}