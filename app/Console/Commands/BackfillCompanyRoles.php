<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyRoleService;
use Illuminate\Console\Command;

class BackfillCompanyRoles extends Command
{
    protected $signature = 'app:backfill-company-roles {--dry-run : Report the migration without changing assignments}';

    protected $description = 'Create default company roles and move existing company users from legacy platform templates.';

    public function handle(CompanyRoleService $roles): int
    {
        $migrated = 0;
        $permissionsAdded = 0;

        if (! $this->option('dry-run')) {
            Company::query()->orderBy('id')->each(function (Company $company) use ($roles, &$permissionsAdded): void {
                $permissionsAdded += $roles->syncNewDefaultPermissions($company);
            });
        }

        User::query()->whereNotNull('company_id')->whereIn('role', CompanyRoleService::DEFAULT_COMPANY_ROLES)
            ->orderBy('id')->each(function (User $user) use ($roles, &$migrated): void {
                $role = $roles->roleForUser($user, $user->role);
                $this->line("{$user->email}: {$user->role} → company role #{$role->id}");

                if (! $this->option('dry-run')) {
                    $user->syncRoles([$role]);
                }

                $migrated++;
            });

        $this->info(($this->option('dry-run') ? 'Would migrate' : 'Migrated')." {$migrated} user(s).");

        if (! $this->option('dry-run')) {
            $this->info("Added {$permissionsAdded} newly introduced default permission(s) to company roles.");
        }

        return self::SUCCESS;
    }
}
