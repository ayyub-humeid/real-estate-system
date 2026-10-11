<?php

namespace App\Policies;

use App\Models\{ListingPriceHistory, User};
use App\Policies\Concerns\CompanyOwnedPolicy;

class ListingPriceHistoryPolicy
{
    use CompanyOwnedPolicy;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_listing');
    }
    public function view(User $user, ListingPriceHistory $history): bool
    {
        return $this->canForRecord($user, 'view_listing', $history->listing);
    }
}