<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL created a check constraint for the original Laravel enum.
        // Drop only that generated constraint, then use the same Schema Builder
        // VARCHAR change on both engines. MySQL has no equivalent constraint.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE units DROP CONSTRAINT IF EXISTS units_status_check');
        }

        Schema::table('units', function (Blueprint $table): void {
            $table->string('status', 32)->default('draft')->change();
        });

        DB::table('units')->where('status', '<>', 'maintenance')->update(['status' => 'ready']);
        DB::table('units')->where('status', 'maintenance')->update(['status' => 'maintenance']);

        // The entries below were generated only by the discarded legacy-status
        // bridge and must not survive as operational history.
        DB::table('unit_status_histories')->where('notes', 'like', 'Phase 06 physical-status cutover.%')->delete();

        // These branches only repair an installation that ran an earlier local
        // Phase 06 draft. A clean migration path never creates that column.
        $indexes = collect(Schema::getIndexes('units'))->pluck('name')->all();
        $hasInterimOperationalStatus = Schema::hasColumn('units', 'operational_status');

        Schema::table('units', function (Blueprint $table) use ($indexes, $hasInterimOperationalStatus): void {
            if (in_array('units_company_status_index', $indexes, true)) {
                $table->dropIndex('units_company_status_index');
            }
            if (in_array('units_project_status_index', $indexes, true)) {
                $table->dropIndex('units_project_status_index');
            }
            if (in_array('units_company_operational_index', $indexes, true)) {
                $table->dropIndex('units_company_operational_index');
            }
            if (in_array('units_project_operational_index', $indexes, true)) {
                $table->dropIndex('units_project_operational_index');
            }
            if ($hasInterimOperationalStatus) {
                $table->dropColumn('operational_status');
            }
            if (! in_array('units_company_physical_status_index', $indexes, true)) {
                $table->index(['company_id', 'status'], 'units_company_physical_status_index');
            }
            if (! in_array('units_project_physical_status_index', $indexes, true)) {
                $table->index(['project_id', 'status'], 'units_project_physical_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropIndex('units_company_physical_status_index');
            $table->dropIndex('units_project_physical_status_index');
        });
    }
};
