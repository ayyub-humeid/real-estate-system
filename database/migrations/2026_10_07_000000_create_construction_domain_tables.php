<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_constructions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('planned');
            $table->date('planned_start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->foreignId('manager_party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status'], 'construction_company_status_idx');
        });

        Schema::create('construction_work_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_construction_id');
            $table->foreign('project_construction_id', 'cwp_construction_fk')->references('id')->on('project_constructions')->cascadeOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('responsible_party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('planned');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['project_construction_id', 'status'], 'cwp_construction_status_idx');
            $table->index('budget_line_id', 'cwp_budget_line_idx');
            $table->index('responsible_party_id', 'cwp_party_idx');
        });

        Schema::create('construction_work_package_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('construction_work_package_id');
            $table->foreign('construction_work_package_id', 'cwpt_package_fk')->references('id')->on('construction_work_packages')->cascadeOnDelete();
            $table->foreignId('assigned_party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('planned');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->boolean('requires_inspection')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['construction_work_package_id', 'status'], 'cwpt_package_status_idx');
            $table->index('assigned_party_id', 'cwpt_party_idx');
        });

        Schema::create('construction_progress_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('construction_work_package_task_id');
            $table->foreign('construction_work_package_task_id', 'cpu_task_fk')->references('id')->on('construction_work_package_tasks')->cascadeOnDelete();
            $table->decimal('progress_percentage', 5, 2);
            $table->date('reported_at');
            $table->text('notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrects_progress_update_id')->nullable();
            $table->foreign('corrects_progress_update_id', 'cpu_corrects_fk')->references('id')->on('construction_progress_updates')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestamps();
            $table->index(['construction_work_package_task_id', 'id'], 'cpu_task_id_idx');
            $table->index(['construction_work_package_task_id', 'reported_at'], 'cpu_task_date_idx');
        });

        Schema::create('construction_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('construction_work_package_task_id');
            $table->foreign('construction_work_package_task_id', 'ci_task_fk')->references('id')->on('construction_work_package_tasks')->cascadeOnDelete();
            $table->foreignId('inspector_party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->date('inspection_date');
            $table->string('result');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['construction_work_package_task_id', 'inspection_date'], 'ci_task_date_idx');
            $table->index(['construction_work_package_task_id', 'result'], 'ci_task_result_idx');
        });

        Schema::create('construction_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('construction_work_package_id');
            $table->foreign('construction_work_package_id', 'cissue_package_fk')->references('id')->on('construction_work_packages')->cascadeOnDelete();
            $table->foreignId('construction_work_package_task_id')->nullable();
            $table->foreign('construction_work_package_task_id', 'cissue_task_fk')->references('id')->on('construction_work_package_tasks')->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->date('opened_at');
            $table->date('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['construction_work_package_id', 'status'], 'cissue_package_status_idx');
            $table->index(['construction_work_package_task_id', 'status'], 'cissue_task_status_idx');
            $table->index(['severity', 'status'], 'cissue_severity_status_idx');
            $table->index('assigned_to_user_id', 'cissue_assignee_idx');
        });

        Schema::create('construction_delays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('construction_work_package_id');
            $table->foreign('construction_work_package_id', 'cdelay_package_fk')->references('id')->on('construction_work_packages')->cascadeOnDelete();
            $table->foreignId('construction_work_package_task_id')->nullable();
            $table->foreign('construction_work_package_task_id', 'cdelay_task_fk')->references('id')->on('construction_work_package_tasks')->restrictOnDelete();
            $table->date('baseline_end_date');
            $table->date('revised_end_date');
            $table->string('reason_code');
            $table->text('description')->nullable();
            $table->date('reported_at');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['construction_work_package_id', 'reported_at'], 'cdelay_package_date_idx');
            $table->index(['construction_work_package_task_id', 'reported_at'], 'cdelay_task_date_idx');
        });
    }

    public function down(): void
    {
        foreach (['construction_delays', 'construction_issues', 'construction_inspections', 'construction_progress_updates', 'construction_work_package_tasks', 'construction_work_packages', 'project_constructions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
