<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_offer_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'year'], 'crm_offer_number_sequence_unique');
        });

        Schema::create('crm_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number', 32);
            $table->string('transaction_type', 16);
            $table->string('status', 16)->default('draft');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('next_revision_number')->default(1);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference_number'], 'crm_offer_company_reference_unique');
            $table->index(['company_id', 'opportunity_id', 'status'], 'crm_offer_company_opp_status_idx');
        });

        Schema::create('crm_offer_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained('crm_offers')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('proposed_by_side', 16);
            $table->string('status', 16)->default('draft');
            $table->decimal('amount', 16, 2);
            $table->string('currency', 3);
            $table->decimal('down_payment_amount', 16, 2)->nullable();
            $table->string('payment_method', 64)->nullable();
            $table->text('payment_terms')->nullable();
            $table->string('rent_frequency', 32)->nullable();
            $table->unsignedInteger('lease_term_value')->nullable();
            $table->string('lease_term_unit', 16)->nullable();
            $table->decimal('deposit_amount', 16, 2)->nullable();
            $table->json('terms_json')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['offer_id', 'revision_number'], 'crm_offer_revision_number_unique');
            $table->index(['company_id', 'offer_id', 'status'], 'crm_offer_revision_company_offer_status_idx');
        });

        Schema::create('crm_offer_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_revision_id')->constrained('crm_offer_revisions')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'assigned_approver_id', 'status'], 'crm_offer_approval_company_assignee_status_idx');
            $table->index(['offer_revision_id', 'status'], 'crm_offer_approval_revision_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_offer_approvals');
        Schema::dropIfExists('crm_offer_revisions');
        Schema::dropIfExists('crm_offers');
        Schema::dropIfExists('crm_offer_number_sequences');
    }
};
