<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // A rental is agreed per period; the contract total is derived from it.
        Schema::table('contract_versions', function (Blueprint $table): void {
            $table->decimal('rent_amount', 18, 2)->nullable()->after('total_amount');
            $table->string('rent_frequency', 32)->nullable()->after('rent_amount');
        });

        // One extra header line companies asked for (branch, licence, Arabic legal name).
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->string('contract_header_note', 255)->nullable()->after('contract_footer_text');
        });

        // Seeing the rent roll is its own right, like any other Filament page.
        DB::table('permissions')->insertOrIgnore([
            'name' => 'page_RentRoll', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Roles that already follow contract money keep seeing it.
        $permissionId = DB::table('permissions')->where('name', 'page_RentRoll')->where('guard_name', 'web')->value('id');
        $existing = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'view_contract_financials')
            ->where('permissions.guard_name', 'web')
            ->pluck('role_has_permissions.role_id');

        foreach ($existing as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'page_RentRoll')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn('contract_header_note');
        });

        Schema::table('contract_versions', function (Blueprint $table): void {
            $table->dropColumn(['rent_amount', 'rent_frequency']);
        });
    }
};
