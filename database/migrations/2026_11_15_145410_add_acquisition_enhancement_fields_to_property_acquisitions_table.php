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
        Schema::table('property_acquisitions', function (Blueprint $table) {
            $table->decimal('total_land_area_m2', 15, 2)->nullable()->after('currency');
            $table->string('title_deed_number')->nullable()->after('total_land_area_m2');
            $table->foreignId('seller_party_id')->nullable()->constrained('parties')->nullOnDelete()->after('title_deed_number');
            $table->decimal('latitude', 10, 8)->nullable()->after('seller_party_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->text('location_details')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_acquisitions', function (Blueprint $table) {
            $table->dropForeign(['seller_party_id']);
            $table->dropColumn([
                'total_land_area_m2',
                'title_deed_number',
                'seller_party_id',
                'latitude',
                'longitude',
                'location_details',
            ]);
        });
    }
};