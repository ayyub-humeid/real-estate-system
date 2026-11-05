<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_version_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('supersedes_schedule_id')->nullable()->constrained('contract_payment_schedules')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('currency', 3);
            $table->decimal('total_amount', 18, 4);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'revision_number'], 'contract_schedule_contract_revision_unique');
            $table->index(['company_id', 'contract_id', 'status'], 'contract_schedule_company_contract_status_idx');
            $table->index(['company_id', 'assigned_to', 'status'], 'contract_schedule_company_assignee_status_idx');
        });

        Schema::create('contract_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_payment_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('replaces_installment_id')->nullable()->constrained('contract_installments')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('kind', 32)->default('installment');
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3);
            $table->date('due_date');
            $table->string('status', 24)->default('scheduled');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['contract_payment_schedule_id', 'sequence'], 'contract_installment_schedule_sequence_unique');
            $table->index(['company_id', 'status', 'due_date'], 'contract_installment_company_status_due_idx');
            $table->index(['contract_payment_schedule_id', 'due_date'], 'contract_installment_schedule_due_idx');
        });

        Schema::create('contract_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_installment_id')->constrained()->restrictOnDelete();
            $table->decimal('allocated_amount', 18, 4);
            $table->string('currency', 3);
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['payment_id'], 'contract_payment_allocation_payment_idx');
            $table->index(['contract_installment_id'], 'contract_payment_allocation_installment_idx');
        });

        Schema::create('contract_financial_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('idempotency_key', 160);
            $table->json('payload')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key'], 'contract_financial_event_company_key_unique');
            $table->index(['company_id', 'event_type', 'occurred_at'], 'contract_financial_event_company_type_time_idx');
            $table->index(['contract_id', 'occurred_at'], 'contract_financial_event_contract_time_idx');
        });

        Schema::create('contract_financial_notification_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_fallback')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'event_type', 'user_id'], 'contract_financial_route_company_event_user_unique');
            $table->index(['company_id', 'event_type', 'is_fallback'], 'contract_financial_route_lookup_idx');
        });

        Schema::create('contract_financial_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            // MySQL limits identifiers to 64 characters; Laravel's generated
            // foreign-key name for this table would exceed that limit.
            $table->unsignedBigInteger('contract_financial_event_id');
            $table->foreign('contract_financial_event_id', 'contract_fin_delivery_event_fk')
                ->references('id')
                ->on('contract_financial_events')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 32)->default('database');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_financial_event_id', 'user_id', 'channel'], 'contract_financial_notification_delivery_unique');
        });

        Schema::table('company_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_settings', 'contract_payment_reminder_days')) {
                $table->unsignedSmallInteger('contract_payment_reminder_days')->default(3);
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('contract_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('contract_payment_schedule_id')->nullable()->after('contract_id')->constrained()->nullOnDelete();
            $table->foreignId('reversal_of_payment_id')->nullable()->after('reference_number')->constrained('payments')->nullOnDelete();
            $table->string('payment_scope', 32)->default('project_cost')->after('direction');
            $table->string('idempotency_key', 160)->nullable()->after('payment_scope');
            $table->decimal('project_amount', 18, 4)->nullable()->change();
            $table->index(['company_id', 'contract_id', 'status'], 'payment_company_contract_status_idx');
            $table->unique(['company_id', 'idempotency_key'], 'payment_company_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payment_company_idempotency_key_unique');
            $table->dropIndex('payment_company_contract_status_idx');
            $table->dropConstrainedForeignId('reversal_of_payment_id');
            $table->dropConstrainedForeignId('contract_payment_schedule_id');
            $table->dropConstrainedForeignId('contract_id');
            $table->dropColumn(['payment_scope', 'idempotency_key']);
            $table->decimal('project_amount', 18, 4)->nullable(false)->change();
        });

        Schema::table('company_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('company_settings', 'contract_payment_reminder_days')) {
                $table->dropColumn('contract_payment_reminder_days');
            }
        });

        Schema::dropIfExists('contract_financial_notification_deliveries');
        Schema::dropIfExists('contract_financial_notification_routes');
        Schema::dropIfExists('contract_financial_events');
        Schema::dropIfExists('contract_payment_allocations');
        Schema::dropIfExists('contract_installments');
        Schema::dropIfExists('contract_payment_schedules');
    }
};
