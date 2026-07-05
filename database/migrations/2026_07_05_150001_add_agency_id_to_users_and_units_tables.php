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
        // إضافة agency_id إلى جدول users (الوكلاء)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agency_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('agencies')
                  ->nullOnDelete();
        });

        // إضافة agency_id إلى جدول units (الوحدات العقارية)
        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('agency_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('agencies')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
            $table->dropColumn('agency_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
            $table->dropColumn('agency_id');
        });
    }
};
