<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\CompanyRoleService;
use App\Support\StandardRoles;
use Illuminate\Console\Command;

class InstallStandardRoles extends Command
{
    protected $signature = 'roles:install-standard {--company= : Only this company id}';

    protected $description = 'Create the ready-made sales and project roles for companies that do not have them yet (existing roles are never changed).';

    public function handle(CompanyRoleService $roles): int
    {
        $created = 0;

        Company::withoutGlobalScopes()
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->each(function (Company $company) use ($roles, &$created): void {
                $result = $roles->installStandardRoles($company);
                $created += count($result['created']);
                $this->line("{$company->name}: created ".(implode(', ', $result['created']) ?: '—').'; kept '.(implode(', ', $result['skipped']) ?: '—'));
            });

        $this->info("Created {$created} role(s) from ".count(StandardRoles::names()).' standard templates.');

        return self::SUCCESS;
    }
}
