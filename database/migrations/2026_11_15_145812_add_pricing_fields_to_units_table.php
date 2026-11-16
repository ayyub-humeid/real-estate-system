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
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('sale_price', 18, 2)->nullable()->after('unit_number');
            $table->string('sale_currency', 3)->nullable()->after('sale_price');
            $table->decimal('rent_price', 18, 2)->nullable()->change();
            $table->string('rent_currency', 3)->nullable()->after('rent_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn([
                'sale_price',
                'sale_currency',
                'rent_currency',
            ]);
            $table->decimal('rent_price', 18, 2)->nullable()->change();
        });
    }
};