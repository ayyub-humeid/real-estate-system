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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Relationships
            $table->foreignId('lease_id')->constrained()->onDelete('cascade');
            
            // Payment Details
            $table->decimal('amount', 10, 2);
            $table->date('due_date'); // When payment is due
            $table->date('payment_date')->nullable(); // When actually paid (NULL = unpaid)
            
            // Payment Info
            $table->enum('payment_method', ['cash', 'bank_transfer', 'check', 'credit_card', 'online', 'other'])->nullable();
            $table->string('reference_number')->nullable(); // Transaction reference
            $table->string('check_number')->nullable(); // For check payments
            
            // Status
            $table->enum('status', ['pending', 'paid', 'overdue', 'partial', 'cancelled'])->default('pending');
            $table->decimal('paid_amount', 10, 2)->default(0); // For partial payments
            $table->decimal('remaining_amount', 10, 2)->default(0); // Balance due
            
            // Additional Info
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users'); // Who recorded the payment
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['lease_id', 'status']);
            $table->index('payment_date');
            $table->index('due_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
