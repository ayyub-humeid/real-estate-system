<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the unit a customer is interested in travel Opportunity -> Offer ->
 * Reservation -> Contract without being picked again. Listings are switched
 * off (features.advertisements), so they cannot carry the unit any more.
 * Additive and nullable: existing rows are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('listing_id')->constrained('projects')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('project_id')->constrained('units')->nullOnDelete();
            $table->index(['company_id', 'unit_id'], 'opportunities_company_unit_idx');
        });

        Schema::table('crm_offers', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->after('listing_id')->constrained('units')->nullOnDelete();
            $table->index(['company_id', 'unit_id'], 'crm_offers_company_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_offers', function (Blueprint $table): void {
            $table->dropIndex('crm_offers_company_unit_idx');
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropIndex('opportunities_company_unit_idx');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
