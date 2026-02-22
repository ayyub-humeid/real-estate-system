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
        Schema::create('leases', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');

            // Lease Details
            $table->date('start_date');
            $table->date('end_date')->nullable(); // NULL = open-ended lease
            $table->decimal('rent_amount', 10, 2);
            $table->decimal('deposit_amount', 10, 2)->nullable();

            // Payment Terms
            $table->enum('payment_frequency', ['monthly', 'quarterly', 'semi_annually', 'yearly'])->default('monthly');
            $table->integer('payment_day')->default(1); // Day of month rent is due

            // Status
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'renewed'])->default('draft');
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();

            // Additional Info
            $table->text('notes')->nullable();
            $table->text('special_terms')->nullable(); // Special conditions

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['unit_id', 'status']);
            $table->index(['tenant_id']);
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
