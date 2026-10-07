<?php

use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
            $table->foreignId('planned_unit_id')->nullable()->after('project_id')->constrained('project_planned_units')->nullOnDelete();
            $table->decimal('actual_area', 12, 2)->nullable()->after('sqft');
            $table->string('area_unit', 16)->nullable()->after('actual_area');
            $table->string('location_label')->nullable()->after('area_unit');
            $table->timestamp('ready_at')->nullable()->after('location_label');
            $table->timestamp('inactive_at')->nullable()->after('ready_at');
            $table->text('inactive_reason')->nullable()->after('inactive_at');
            $table->index(['company_id', 'status'], 'units_company_physical_status_index');
            $table->index(['project_id', 'status'], 'units_project_physical_status_index');
            $table->unique('planned_unit_id', 'units_planned_unit_unique');
        });

        Schema::table('unit_features', function (Blueprint $table): void {
            $table->string('feature_key')->nullable()->after('unit_id');
            $table->unique(['unit_id', 'feature_key'], 'unit_features_unit_key_unique');
        });

        Schema::create('unit_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['unit_id', 'changed_at'], 'unit_status_history_unit_changed_index');
            $table->index(['company_id', 'to_status'], 'unit_status_history_company_status_index');
        });

        Schema::create('unit_ownerships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->decimal('ownership_percentage', 5, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'end_date'], 'unit_ownership_unit_end_index');
            $table->index(['company_id', 'party_id'], 'unit_ownership_company_party_index');
        });

        Unit::withoutGlobalScopes()->orderBy('id')->each(function (Unit $unit): void {
            $unit->forceFill([
                'actual_area' => $unit->sqft ? $unit->sqft : null,
                'area_unit' => $unit->sqft ? 'sqft' : null,
                'ready_at' => $unit->status === 'maintenance' ? null : $unit->updated_at,
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_ownerships');
        Schema::dropIfExists('unit_status_histories');
        Schema::table('unit_features', function (Blueprint $table): void {
            $table->dropUnique('unit_features_unit_key_unique');
            $table->dropColumn('feature_key');
        });
        Schema::table('units', function (Blueprint $table): void {
            $table->dropUnique('units_planned_unit_unique');
            $table->dropIndex('units_company_physical_status_index');
            $table->dropIndex('units_project_physical_status_index');
            $table->dropConstrainedForeignId('planned_unit_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['actual_area', 'area_unit', 'location_label', 'ready_at', 'inactive_at', 'inactive_reason']);
        });
    }
};
