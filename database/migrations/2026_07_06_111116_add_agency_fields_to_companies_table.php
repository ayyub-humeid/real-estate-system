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
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('is_active');
            $table->string('relation')->nullable()->after('verified');
            $table->string('badge')->nullable()->after('relation');
            $table->string('badge_type')->nullable()->after('badge');
            $table->string('hq')->nullable()->after('badge_type');
            $table->string('branches')->nullable()->after('hq');
            $table->float('rating')->default(0.0)->after('branches');
            $table->integer('years_active')->default(1)->after('rating');
            $table->string('partner_developers')->nullable()->after('years_active');
            $table->string('about_title')->nullable()->after('partner_developers');
            $table->text('about_description')->nullable()->after('about_title');
            $table->text('about_sub_description')->nullable()->after('about_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'verified',
                'relation',
                'badge',
                'badge_type',
                'hq',
                'branches',
                'rating',
                'years_active',
                'partner_developers',
                'about_title',
                'about_description',
                'about_sub_description',
            ]);
        });
    }
};
