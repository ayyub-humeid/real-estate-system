<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('tenants'));
        $hasLegacyUnique = $indexes->contains(
            fn (array $index): bool => $index['name'] === 'tenants_user_id_unique'
        );
        $hasCompositeUnique = $indexes->contains(
            fn (array $index): bool => $index['name'] === 'user_company_unique'
        );

        Schema::table('tenants', function (Blueprint $table) use ($hasLegacyUnique, $hasCompositeUnique): void {
            if ($hasLegacyUnique) {
                $table->dropUnique('tenants_user_id_unique');
            }

            if (! $hasCompositeUnique) {
                $table->unique(['user_id', 'company_id'], 'user_company_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $hasCompositeUnique = collect(Schema::getIndexes('tenants'))
            ->contains(fn (array $index): bool => $index['name'] === 'user_company_unique');

        if ($hasCompositeUnique) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropUnique('user_company_unique');
            });
        }
    }
};
