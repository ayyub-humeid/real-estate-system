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
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();           // مسار الصورة أو الرابط
            $table->boolean('verified')->default(false);
            $table->string('relation')->nullable();       // مثل: Subsidiary of Apex Group
            $table->string('badge')->nullable();          // مثل: Elite Partner, Exclusive
            $table->string('badge_type')->nullable();     // نوع التنسيق: elite, exclusive, high_growth
            $table->string('hq')->nullable();             // الفرع الرئيسي
            $table->string('branches')->nullable();       // الفروع الأخرى
            $table->float('rating')->default(0.0);
            $table->integer('years_active')->default(1);
            $table->string('partner_developers')->nullable(); // مثل: Emaar, Damac
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('about_title')->nullable();
            $table->text('about_description')->nullable();
            $table->text('about_sub_description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
