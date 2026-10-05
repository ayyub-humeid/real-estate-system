<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Older Laravel/MySQL combinations created this as a normal index when
        // `foreignId()->unique()->constrained()` was chained. A clean schema
        // must support either historical shape before adding the composite key.
        $hasSingleUserUnique = collect(Schema::getIndexes('tenants'))
            ->contains(fn (array $index): bool => $index['name'] === 'tenants_user_id_unique');

        Schema::table('tenants', function (Blueprint $table) use ($hasSingleUserUnique) {
            if ($hasSingleUserUnique) {
                $table->dropUnique('tenants_user_id_unique');
            }
            $table->unique(['user_id', 'company_id'], 'user_company_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique('user_company_unique');
            // This might fail if duplicates exist, but it's the right rollback path.
            $table->unique('user_id');
        });
    }
};
