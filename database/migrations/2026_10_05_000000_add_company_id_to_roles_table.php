<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('roles_name_guard_name_unique');
            $table->unique(['company_id', 'name', 'guard_name'], 'roles_company_name_guard_unique');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('roles_company_name_guard_unique');
            $table->dropConstrainedForeignId('company_id');
            $table->unique(['name', 'guard_name']);
        });
    }
};
