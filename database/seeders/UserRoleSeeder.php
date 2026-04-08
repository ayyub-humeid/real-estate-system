<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $company = Company::create(['name' => 'Demo Company']);
        }

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'super@admin.com',
                'password' => '123456',
                'role' => 'super_admin',
                'company_id' => null,
            ],
            [
                'name' => 'Company Admin',
                'email' => 'company@admin.com',
                'password' => Hash::make('123456'),
                'role' => 'company_admin',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Property Manager',
                'email' => 'pm@admin.com',
                'password' => Hash::make('123456'),
                'role' => 'property_manager',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Financial Manager',
                'email' => 'fin@admin.com',
                'password' => Hash::make('123456'),
                'role' => 'financial_manager',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Tenant User',
                'email' => 'tenant@admin.com',
                'password' => Hash::make('123456'),
                'role' => 'tenant',
                'company_id' => $company->id,
            ],
        ];

        foreach ($users as $userData) {
            $roleName = $userData['role'];
            unset($userData['role']);
            
            // Ensure the role exists first (using Spatie Role)
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Directly assign role via Spatie HasRoles (used by Shield)
            if (!$user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }
        
        echo "Successfully created test users with dedicated roles.\n";
    }
}
