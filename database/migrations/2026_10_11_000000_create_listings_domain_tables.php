<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->boolean('allow_maintenance_unit_marketing')->default(false)->after('late_payment_fee_percentage');
        });

        Schema::create('listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('listable_type', 32);
            $table->unsignedBigInteger('listable_id');
            $table->string('listing_type', 16);
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('asking_price', 14, 2);
            $table->string('currency', 3);
            $table->string('rent_frequency', 24)->nullable();
            $table->decimal('security_deposit', 14, 2)->nullable();
            $table->unsignedInteger('minimum_lease_months')->nullable();
            $table->text('availability_notes')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status'], 'listings_company_status_index');
            $table->index(['company_id', 'listing_type'], 'listings_company_type_index');
            $table->index(['listable_type', 'listable_id'], 'listings_listable_index');
        });

        Schema::create('listing_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 14, 2)->nullable();
            $table->decimal('new_price', 14, 2);
            $table->string('old_currency', 3)->nullable();
            $table->string('new_currency', 3);
            $table->string('old_rent_frequency', 24)->nullable();
            $table->string('new_rent_frequency', 24)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['listing_id', 'created_at'], 'listing_price_history_listing_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_price_histories');
        Schema::dropIfExists('listings');
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn('allow_maintenance_unit_marketing');
        });
    }
};
