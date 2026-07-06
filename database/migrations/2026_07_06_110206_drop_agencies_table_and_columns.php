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
        // 1. Drop foreign keys and columns from units and users tables
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
            $table->dropColumn('agency_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
            $table->dropColumn('agency_id');
        });

        // 2. Drop agencies table
        Schema::dropIfExists('agencies');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Re-create agencies table
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->boolean('verified')->default(false);
            $table->string('relation')->nullable();
            $table->string('badge')->nullable();
            $table->string('badge_type')->nullable();
            $table->string('hq')->nullable();
            $table->string('branches')->nullable();
            $table->float('rating')->default(0.0);
            $table->integer('years_active')->default(1);
            $table->string('partner_developers')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('about_title')->nullable();
            $table->text('about_description')->nullable();
            $table->text('about_sub_description')->nullable();
            $table->timestamps();
        });

        // 2. Re-add agency_id to users and units
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agency_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('agencies')
                  ->nullOnDelete();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('agency_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('agencies')
                  ->nullOnDelete();
        });
    }
};
