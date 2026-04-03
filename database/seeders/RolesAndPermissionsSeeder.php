<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // The super_admin role is created automatically by Filament Shield.
        
        // 1. Company Admin role
        $companyAdmin = Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
        // Company admins can do almost everything within their company, except manage system-level roles
        $companyAdminPermissions = Permission::whereNotIn('name', [
            'view_role', 'view_any_role', 'create_role', 'update_role', 'delete_role', 'delete_any_role', 'force_delete_role', 'force_delete_any_role', 'reorder_role', 'restore_role', 'restore_any_role'
        ])->get();
        $companyAdmin->syncPermissions($companyAdminPermissions);

        // 2. Property Manager role
        $propertyManager = Role::firstOrCreate(['name' => 'property_manager', 'guard_name' => 'web']);
        $propertyManagerPermissions = Permission::where(function ($query) {
            $query->where('name', 'like', '%property%')
                  ->orWhere('name', 'like', '%unit%')
                  ->orWhere('name', 'like', '%image%')
                  ->orWhere('name', 'like', '%maintenance%')
                  ->orWhere('name', 'like', '%rental%')
                  ->orWhere('name', 'like', '%location%');
        })->get();
        $propertyManager->syncPermissions($propertyManagerPermissions);

        // 3. Financial Manager role
        $financialManager = Role::firstOrCreate(['name' => 'financial_manager', 'guard_name' => 'web']);
        $financialManagerPermissions = Permission::where(function ($query) {
            $query->where('name', 'like', '%_lease')
                  ->orWhere('name', 'like', '%_payment')
                  ->orWhere('name', 'like', '%_expense')
                  ->orWhere('name', 'like', '%_document')
                  ->orWhere('name', 'like', '%_tenant')
                  ->orWhere('name', 'like', '%_company');
        })->get();
        $financialManager->syncPermissions($financialManagerPermissions);
    }
}
