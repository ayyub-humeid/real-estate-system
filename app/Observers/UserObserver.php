<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "saved" event.
     */
    public function saved(User $user): void
    {
        // If the 'role' column was present in the save data
        if ($user->wasChanged('role') || $user->wasRecentlyCreated) {
            if ($user->role) {
                // Ensure the role exists in Spatie (safety check)
                $role = \Spatie\Permission\Models\Role::where('name', $user->role)->first();
                
                if ($role) {
                    $user->syncRoles([$role->name]);
                }
            }
        }

        // Notify new users
        if ($user->wasRecentlyCreated) {
            $user->notify(new \App\Notifications\WelcomeNotification());
        }
    }
}
