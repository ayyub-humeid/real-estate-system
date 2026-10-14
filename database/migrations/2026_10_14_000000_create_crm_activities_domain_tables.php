<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_follow_up_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('status', 24)->default('pending');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'assigned_to', 'status', 'due_at'], 'crm_task_company_assignee_status_due_idx');
            $table->index(['company_id', 'opportunity_id', 'created_at'], 'crm_task_company_opportunity_date_idx');
            $table->index(['company_id', 'lead_id', 'created_at'], 'crm_task_company_lead_date_idx');
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('crm_follow_up_tasks')->nullOnDelete();
            $table->string('type', 24);
            $table->string('outcome', 24)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            // A completion is idempotent even if two requests race.
            $table->unique('task_id', 'crm_activity_task_unique');
            $table->index(['company_id', 'opportunity_id', 'type', 'outcome'], 'crm_activity_company_opp_type_outcome_idx');
            $table->index(['company_id', 'lead_id', 'occurred_at'], 'crm_activity_company_lead_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_follow_up_tasks');
    }
};
