<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pricing is a later commercial concern. Actual Unit setup must not
        // invent a rent value merely to satisfy this historical column.
        Schema::table('units', function (Blueprint $table): void {
            $table->decimal('rent_price', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->decimal('rent_price', 10, 2)->nullable(false)->change();
        });
    }
};
