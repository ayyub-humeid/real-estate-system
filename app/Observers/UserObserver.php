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
                // ✅ FIX: Use firstOrCreate instead of a plain lookup.
                // Previously, if the Spatie role row didn't exist yet (e.g. 'tenant'
                // was never seeded), the lookup returned null and syncRoles() was
                // silently skipped — the user kept role='tenant' on the column but
                // had NO actual Spatie role, breaking any role-based access checks.
                $role = \Spatie\Permission\Models\Role::firstOrCreate([
                    'name' => $user->role,
                    'guard_name' => 'web',
                ]);

                $user->syncRoles([$role->name]);
            }
        }

        // Notify new users
        if ($user->wasRecentlyCreated) {
            $user->notify(new \App\Notifications\WelcomeNotification());
        }
    }
}
