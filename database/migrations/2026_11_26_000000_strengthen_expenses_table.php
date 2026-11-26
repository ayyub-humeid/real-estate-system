<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const WORKFLOW_PERMISSIONS = ['pay_expense', 'cancel_expense'];

    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
            $table->foreignId('party_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->date('salary_month')->nullable()->after('expense_date');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->index(['company_id', 'expense_date']);
            $table->index(['employee_id', 'salary_month']);
        });

        // Records saved as paid before the date was mandatory.
        DB::table('expenses')->where('status', 'paid')->whereNull('paid_at')->update(['paid_at' => DB::raw('expense_date')]);

        // Roles that could already edit expenses keep the ability to settle and cancel them.
        $editors = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'update_expense')
            ->where('permissions.guard_name', 'web')
            ->pluck('role_has_permissions.role_id');

        foreach (self::WORKFLOW_PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

            foreach ($editors as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::WORKFLOW_PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('expenses', function (Blueprint $table) {
            // MySQL needs the foreign keys gone before the indexes they rely on.
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['party_id']);
            $table->dropForeign(['cancelled_by']);
            $table->dropIndex(['employee_id', 'salary_month']);
            // company_id keeps its other (company_id, status) index for its foreign key.
            $table->dropIndex(['company_id', 'expense_date']);
            $table->dropColumn(['employee_id', 'party_id', 'salary_month', 'cancelled_at', 'cancelled_by', 'cancellation_reason']);
        });
    }
};
