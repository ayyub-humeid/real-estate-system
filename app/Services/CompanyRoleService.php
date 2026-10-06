<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class CompanyRoleService
{
    public const DEFAULT_COMPANY_ROLES = ['company_admin', 'property_manager', 'financial_manager'];

    /** Platform administration is never delegated through a company-owned role. */
    private const PLATFORM_PERMISSION_TERMS = ['subscription', 'plan', 'role', 'super', 'admin', 'user'];

    public function provisionDefaults(Company $company): Collection
    {
        return collect(self::DEFAULT_COMPANY_ROLES)->mapWithKeys(function (string $name) use ($company) {
            // Existing installations may not have run the permissions seeder yet.
            // Creating an empty platform template keeps registration safe; a later
            // seeder run fills the template and new company roles can be reviewed.
            $template = Role::platform()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role = Role::firstOrCreate([
                'company_id' => $company->id,
                'name' => $name,
                'guard_name' => 'web',
            ]);

            if (!$role->permissions()->exists()) {
                $role->syncPermissions($template->permissions->reject(
                    fn(Permission $permission) => $this->isPlatformPermission($permission->name)
                ));
            }

            return [$name => $role];
        });
    }

    /**
     * Release-time synchronization for the built-in company role templates.
     *
     * This only adds permissions that were introduced after a company default
     * role was first provisioned. It never removes a company permission and it
     * never touches company-created custom roles.
     */
    public function syncNewDefaultPermissions(Company $company): int
    {
        $added = 0;
        $roles = $this->provisionDefaults($company);

        foreach (self::DEFAULT_COMPANY_ROLES as $name) {
            $template = Role::platform()->where('name', $name)->where('guard_name', 'web')->firstOrFail();
            $role = $roles->get($name);
            $existingPermissionIds = $role->permissions()->pluck('permissions.id');

            $missing = $template->permissions
                ->reject(fn (Permission $permission): bool => $this->isPlatformPermission($permission->name))
                ->reject(fn (Permission $permission): bool => $existingPermissionIds->contains($permission->id));

            if ($missing->isEmpty()) {
                continue;
            }

            $role->givePermissionTo($missing);
            $added += $missing->count();
        }

        return $added;
    }

    public function roleForUser(User $user, string $name): Role
    {
        if ($user->company_id && $name !== 'tenant' && $name !== 'super_admin') {
            $company = Company::withoutGlobalScopes()->findOrFail($user->company_id);
            $defaults = $this->provisionDefaults($company);

            return $defaults->get($name)
                ?? Role::forCompany($company->id)->where('name', $name)->where('guard_name', 'web')->firstOrFail();
        }

        return Role::platform()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function assignNamedRole(User $user, string $name): void
    {
        if ($name === 'tenant') {
            $this->assignTenantRole($user);
            return;
        }

        if ($name === 'super_admin') {
            $this->assignSuperAdminRole($user);
            return;
        }

        $this->assignRole($user, $this->roleForUser($user, $name));
    }

    /** Tenant is a system role, deliberately separate from Company employee roles. */
    public function assignTenantRole(User $user): void
    {
        $role = Role::platform()->firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
        $user->syncRoles([$role]);
        $user->forceFill(['role' => $role->name])->saveQuietly();
    }

    /** Only internal/bootstrap flows may use this; the Roles UI requires a Super Admin actor. */
    public function assignSuperAdminRole(User $user): void
    {
        $role = Role::platform()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->syncRoles([$role]);
        $user->forceFill(['role' => $role->name])->saveQuietly();
    }

    public function assignSelectedRole(User $user, Role $role, User $actor): void
    {
        if (! $role->company_id && $role->name === 'super_admin' && $actor->isSuperAdmin()) {
            $this->assignSuperAdminRole($user);
            return;
        }

        $this->assignRole($user, $role);
    }

    /** Assign the exact selected Role record; names are never used for this path. */
    public function assignRole(User $user, Role $role): void
    {
        if ($role->company_id && (int) $role->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages(['role_id' => 'The selected role belongs to another company.']);
        }

        if (! $role->company_id) {
            throw ValidationException::withMessages(['role_id' => 'This platform role cannot be assigned to this user.']);
        }

        $user->syncRoles([$role]);

        // Transitional display/cache value for legacy screens. Authorization reads
        // the Spatie role relation, which was assigned above.
        $user->forceFill(['role' => $role->name])->saveQuietly();
    }

    public function visibleTo(User $actor): \Illuminate\Database\Eloquent\Builder
    {
        return $actor->isSuperAdmin()
            ? Role::query()
            : Role::forCompany((int) $actor->company_id);
    }

    public function syncPermissions(User $actor, Role $role, iterable $permissionNames): void
    {
        $this->assertRoleManageable($actor, $role);
        $names = collect($permissionNames)->filter()->unique()->values();

        if ($role->company_id && $names->contains(fn(string $name) => $this->isPlatformPermission($name))) {
            throw ValidationException::withMessages([
                'permissions' => 'Company roles cannot receive platform administration permissions.',
            ]);
        }

        $permissions = Permission::whereIn('name', $names)->where('guard_name', $role->guard_name)->get();
        if ($permissions->count() !== $names->count()) {
            throw ValidationException::withMessages(['permissions' => 'One or more selected permissions do not exist.']);
        }

        $role->syncPermissions($permissions);
    }

    public function assertRoleManageable(User $actor, Role $role): void
    {
        if (! $actor->isSuperAdmin() && (! $actor->company_id || ! $role->company_id || (int) $actor->company_id !== (int) $role->company_id)) {
            throw ValidationException::withMessages(['role' => 'You may only manage roles owned by your current company.']);
        }
    }

    public function isPlatformPermission(string $permission): bool
    {
        // Company Setting is tenant-owned. The Company resource itself is
        // platform administration, but its settings belong to each company.
        if (str_contains($permission, 'company::setting') || str_contains($permission, 'company_setting')) {
            return false;
        }

        if (str_contains($permission, 'company')) {
            return true;
        }

        $tokens = preg_split('/[_:]+/', strtolower($permission));

        return collect(self::PLATFORM_PERMISSION_TERMS)->contains(
            fn (string $term): bool => in_array($term, $tokens, true)
        );
    }
}
