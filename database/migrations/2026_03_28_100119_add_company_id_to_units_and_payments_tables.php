<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add company_id to units
        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')->nullable()->constrained('companies')->onDelete('cascade');
        });

        // 2. Add company_id to payments
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')->nullable()->constrained('companies')->onDelete('cascade');
        });

        // 3. Populate company_id for units from their properties
        DB::statement("UPDATE units JOIN properties ON units.property_id = properties.id SET units.company_id = properties.company_id");

        // 4. Populate company_id for payments from their leases
        DB::statement("UPDATE payments JOIN leases ON payments.lease_id = leases.id SET payments.company_id = leases.company_id");

        // 5. Make company_id NOT NULL after population (optional, but safer)
        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
