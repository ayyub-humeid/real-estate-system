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
        Schema::create('rental_requests', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            // Request Details
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->string('priority')->default('medium'); // low, medium, high

            // Preferred Unit Type
            $table->string('preferred_type')->nullable(); // Apartment, Office, etc.
            $table->decimal('max_budget', 10, 2)->nullable();
            $table->date('desired_move_in')->nullable();
            $table->integer('duration_months')->nullable();

            // Admin Response
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes for common lookups
            $table->index(['company_id', 'status']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_requests');
    }
};
