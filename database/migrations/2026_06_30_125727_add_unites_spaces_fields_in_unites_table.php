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
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedTinyInteger('bedrooms')->nullable()->default(2)->after('rent_price');
            $table->unsignedTinyInteger('bathrooms')->nullable()->default(2)->after('bedrooms');
            $table->unsignedInteger('sqft')->nullable()->default(0)->after('bathrooms');
            $table->dropIndex(['property_id', 'status']);
            $table->index(['property_id', 'status', 'rent_price', 'bedrooms'], 'idx_units_property_search');
        });
    }
 
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
        $table->dropIndex('idx_units_property_search');
        $table->dropIndex(['featured_at']); // حذف فهرس الـ timestamp
        
        // 2. أعد الفهرس القديم
        $table->index(['property_id', 'status']); 
        
        // 3. احذف الأعمدة أخيراً
        $table->dropColumn(['featured_at', 'bedrooms', 'bathrooms', 'sqft']);
            });
    }
};
