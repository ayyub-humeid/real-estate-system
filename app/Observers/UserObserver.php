<?php

namespace App\Observers;

use App\Models\User;
use App\Services\CompanyRoleService;

class UserObserver
{
    /**
     * Handle the User "saved" event.
     */
    public function saved(User $user): void
    {
        // If the 'role' column was present in the save data
        if ($user->wasChanged('role') || $user->wasChanged('company_id') || $user->wasRecentlyCreated) {
            if ($user->role) {
                app(CompanyRoleService::class)->assignNamedRole($user, $user->role);
            }
        }

        // Notify new users
        if ($user->wasRecentlyCreated) {
            $user->notify(new \App\Notifications\WelcomeNotification());
        }
    }
}
