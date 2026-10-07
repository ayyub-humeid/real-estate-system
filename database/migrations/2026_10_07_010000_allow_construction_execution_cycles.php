<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL used the original unique index to support the project FK. Keep a
        // normal FK-supporting index before removing that unique constraint.
        Schema::table('project_constructions', function (Blueprint $table): void {
            $table->index('project_id', 'project_construction_project_fk_idx');
        });

        Schema::table('project_constructions', function (Blueprint $table): void {
            $table->dropUnique('project_constructions_project_id_unique');
            $table->unsignedInteger('execution_number')->default(1)->after('project_id');
            $table->unique(['project_id', 'execution_number'], 'project_construction_execution_unique');
        });
    }

    public function down(): void
    {
        Schema::table('project_constructions', function (Blueprint $table): void {
            $table->dropUnique('project_construction_execution_unique');
            $table->dropColumn('execution_number');
            $table->unique('project_id', 'project_constructions_project_id_unique');
            $table->dropIndex('project_construction_project_fk_idx');
        });
    }
};
