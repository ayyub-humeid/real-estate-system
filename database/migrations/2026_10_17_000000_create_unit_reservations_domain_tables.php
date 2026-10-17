<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_reservation_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'year'], 'unit_res_number_sequence_company_year_unique');
        });

        Schema::create('unit_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reservation_number', 32);
            $table->string('source', 24);
            $table->string('status', 16)->default('draft');
            $table->string('transaction_type', 16);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('crm_offers')->restrictOnDelete();
            $table->foreignId('offer_revision_id')->nullable()->constrained('crm_offer_revisions')->restrictOnDelete();
            $table->decimal('agreed_amount', 16, 2);
            $table->string('currency', 3);
            $table->decimal('down_payment_amount', 16, 2)->nullable();
            $table->string('payment_method', 64)->nullable();
            $table->text('payment_terms')->nullable();
            $table->string('rent_frequency', 32)->nullable();
            $table->unsignedInteger('lease_term_value')->nullable();
            $table->string('lease_term_unit', 16)->nullable();
            $table->decimal('deposit_amount', 16, 2)->nullable();
            $table->json('terms_json')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reservation_number'], 'unit_reservation_company_number_unique');
            $table->index(['company_id', 'unit_id', 'status'], 'unit_reservation_company_unit_status_idx');
            $table->index(['company_id', 'status', 'valid_until'], 'unit_reservation_company_status_until_idx');
            $table->index(['company_id', 'offer_id'], 'unit_reservation_company_offer_idx');
        });

        Schema::create('reservation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('unit_reservations')->cascadeOnDelete();
            $table->json('unit_data');
            $table->json('commercial_terms');
            $table->json('source_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique('reservation_id', 'reservation_snapshot_reservation_unique');
        });

        Schema::create('reservation_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('unit_reservations')->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('action', 48);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['reservation_id', 'created_at', 'id'], 'reservation_status_history_order_idx');
            $table->index(['company_id', 'to_status'], 'reservation_status_history_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_status_histories');
        Schema::dropIfExists('reservation_snapshots');
        Schema::dropIfExists('unit_reservations');
        Schema::dropIfExists('unit_reservation_number_sequences');
    }
};
