<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('currency', 3)->default('USD')->after('project_type');
        });

        // Legacy payments are lease installment obligations, not cash movements.
        Schema::dropIfExists('payments');

        Schema::create('project_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('name')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'version_number'], 'budget_project_version_unique');
            $table->index(['project_id', 'status'], 'budget_project_status_index');
        });

        Schema::create('budget_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('budget_categories')->nullOnDelete();
            $table->foreignId('cloned_from_id')->nullable()->constrained('budget_categories')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_budget_id', 'parent_id'], 'budget_category_parent_index');
        });

        Schema::create('budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('created_from_budget_item_id')->nullable();
            $table->timestamps();
            $table->index('project_id', 'budget_line_project_index');
        });

        Schema::create('budget_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('cloned_from_id')->nullable()->constrained('budget_items')->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('planned_amount', 18, 4);
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('budget_line_id', 'budget_item_line_index');
        });

        Schema::create('financial_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('source');
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference_number')->nullable();
            $table->text('description');
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3);
            $table->decimal('budget_amount', 18, 4);
            $table->decimal('exchange_rate_to_budget', 18, 8)->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'status'], 'commitment_project_status_index');
            $table->index(['budget_item_id', 'status'], 'commitment_item_status_index');
            $table->index(['budget_line_id', 'status'], 'commitment_line_status_index');
            $table->index('party_id', 'commitment_party_index');
        });

        Schema::create('financial_commitment_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_commitment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_change', 18, 4);
            $table->decimal('budget_amount_change', 18, 4);
            $table->string('reason');
            $table->string('status')->default('draft');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['financial_commitment_id', 'status'], 'amendment_commitment_status_index');
        });

        Schema::create('actual_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_commitment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('budget_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3);
            $table->decimal('budget_amount', 18, 4);
            $table->decimal('exchange_rate_to_budget', 18, 8)->nullable();
            $table->date('incurred_at');
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('correction_of_actual_cost_id')->nullable()->constrained('actual_costs')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status'], 'actual_cost_project_status_index');
            $table->index('financial_commitment_id', 'actual_cost_commitment_index');
            $table->index(['budget_line_id', 'status'], 'actual_cost_line_status_index');
            $table->index(['party_id', 'status'], 'actual_cost_party_status_index');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('direction')->default('outgoing');
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3);
            $table->decimal('project_amount', 18, 4);
            $table->decimal('exchange_rate_to_project', 18, 8)->nullable();
            $table->date('payment_date');
            $table->string('reference_number')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status', 'payment_date'], 'payment_project_status_date_index');
            $table->index(['company_id', 'party_id', 'payment_date'], 'payment_company_party_date_index');
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->morphs('allocatable');
            $table->decimal('payment_amount', 18, 4);
            $table->decimal('actual_cost_amount', 18, 4);
            $table->decimal('project_amount', 18, 4);
            $table->decimal('exchange_rate_to_project', 18, 8)->nullable();
            $table->timestamps();
            $table->index('payment_id', 'payment_allocation_payment_index');
            $table->index(['project_id', 'allocatable_type', 'allocatable_id'], 'payment_allocation_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('actual_costs');
        Schema::dropIfExists('financial_commitment_amendments');
        Schema::dropIfExists('financial_commitments');
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budget_categories');
        Schema::dropIfExists('project_budgets');
        Schema::table('projects', function (Blueprint $table): void { $table->dropColumn('currency'); });
    }
};
