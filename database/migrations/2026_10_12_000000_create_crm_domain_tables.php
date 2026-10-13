<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->string('crm_assignment_mode', 32)->default('manual');
            $table->foreignId('crm_round_robin_last_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('crm_pipelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('crm_pipeline_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label');
            $table->string('stage_type', 16)->default('open');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['pipeline_id', 'code']);
            $table->unique(['pipeline_id', 'position']);
            $table->index(['company_id', 'pipeline_id', 'is_active']);
        });

        Schema::create('crm_stage_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained('crm_pipeline_stages')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label');
            $table->string('requirement_type', 32);
            $table->json('config_json')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['pipeline_stage_id', 'key']);
            $table->index(['company_id', 'pipeline_stage_id', 'is_active'], 'crm_stage_req_company_stage_active_idx');
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('normalized_phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('normalized_email')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('first_source', 32)->default('manual');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'normalized_phone']);
            $table->unique(['company_id', 'normalized_email']);
            $table->index(['company_id', 'status', 'assigned_to']);
        });

        Schema::create('lead_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->string('source', 32);
            $table->string('source_reference')->nullable();
            $table->string('contact_result')->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'lead_id', 'created_at']);
        });

        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained('crm_pipeline_stages')->restrictOnDelete();
            $table->string('type', 16);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('budget_min', 16, 2)->nullable();
            $table->decimal('budget_max', 16, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('preferred_property_type')->nullable();
            $table->string('preferred_location')->nullable();
            $table->unsignedInteger('preferred_bedrooms')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('source_reference')->nullable();
            $table->string('lost_reason', 48)->nullable();
            $table->text('closure_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'pipeline_id', 'pipeline_stage_id']);
            $table->index(['company_id', 'assigned_to', 'closed_at']);
            $table->index(['company_id', 'lead_id']);
        });

        Schema::create('opportunity_requirement_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_requirement_id')->constrained('crm_stage_requirements')->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'stage_requirement_id'], 'opp_requirement_completion_unique');
        });

        Schema::create('opportunity_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('crm_pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('crm_pipeline_stages')->restrictOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->json('metadata')->nullable();
            $table->index(['company_id', 'opportunity_id', 'changed_at'], 'opp_stage_history_company_opp_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_stage_histories');
        Schema::dropIfExists('opportunity_requirement_completions');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('lead_inquiries');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('crm_stage_requirements');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('crm_round_robin_last_user_id');
            $table->dropColumn('crm_assignment_mode');
        });
    }
};
