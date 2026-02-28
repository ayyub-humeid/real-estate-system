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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Expense Details
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category'); // maintenance, utilities, salaries, insurance, taxes, other
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('USD');

            // Status & Payment
            $table->string('status')->default('pending'); // pending, paid, cancelled
            $table->date('expense_date');
            $table->date('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // cash, bank_transfer, cheque, card

            // Receipt
            $table->string('receipt_path')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for fast filtering
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'category']);
            $table->index('expense_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
