<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class SyncUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-user-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill Spatie roles for existing users whose role column was never synced (fixes the UserObserver silent-skip bug retroactively)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::whereNotNull('role')->get();

        $this->info("Checking {$users->count()} users...");

        $fixed = 0;

        foreach ($users as $user) {
            if (! $user->hasRole($user->role)) {
                $role = Role::firstOrCreate([
                    'name' => $user->role,
                    'guard_name' => 'web',
                ]);

                $user->syncRoles([$role->name]);

                $this->line("  ↳ Fixed: {$user->email} → assigned role '{$role->name}'");
                $fixed++;
            }
        }

        $this->info("Done. {$fixed} user(s) fixed, " . ($users->count() - $fixed) . ' already correct.');

        return self::SUCCESS;
    }
}
