<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'version_number'], 'doc_versions_document_number_unique');
            $table->index(['company_id', 'document_id'], 'doc_versions_company_document_index');
        });

        Schema::create('project_design_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('discipline')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('planned');
            $table->date('target_submission_date')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'code'], 'design_packages_project_code_unique');
            $table->index(['project_id', 'status'], 'design_packages_project_status_index');
        });

        Schema::create('design_package_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_design_package_id');
            $table->foreign('project_design_package_id', 'dassign_package_fk')->references('id')->on('project_design_packages')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_design_package_id', 'status'], 'design_assignments_package_status_index');
        });

        Schema::create('design_package_scope_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_package_assignment_id');
            $table->foreign('design_package_assignment_id', 'dscope_assignment_fk')->references('id')->on('design_package_assignments')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->date('target_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['design_package_assignment_id', 'status'], 'design_scope_assignment_status_index');
        });

        Schema::create('design_package_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_design_package_id');
            $table->foreign('project_design_package_id', 'dactivity_package_fk')->references('id')->on('project_design_packages')->cascadeOnDelete();
            $table->foreignId('design_package_scope_item_id')->nullable();
            $table->foreign('design_package_scope_item_id', 'dactivity_scope_fk')->references('id')->on('design_package_scope_items')->nullOnDelete();
            $table->string('type');
            $table->text('description');
            $table->timestamp('occurred_at');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['project_design_package_id', 'occurred_at'], 'design_activities_package_occurred_index');
        });

        Schema::create('design_package_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_design_package_id');
            $table->foreign('project_design_package_id', 'dsubmission_package_fk')->references('id')->on('project_design_packages')->cascadeOnDelete();
            $table->foreignId('design_package_assignment_id');
            $table->foreign('design_package_assignment_id', 'dsubmission_assignment_fk')->references('id')->on('design_package_assignments')->restrictOnDelete();
            $table->unsignedInteger('submission_number');
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('submitted');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['project_design_package_id', 'submission_number'], 'design_submissions_package_number_unique');
            $table->index(['project_design_package_id', 'status'], 'design_submissions_package_status_index');
        });

        Schema::create('design_package_submission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_package_submission_id');
            $table->foreign('design_package_submission_id', 'dsubdoc_submission_fk')->references('id')->on('design_package_submissions')->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->restrictOnDelete();
            $table->string('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['design_package_submission_id', 'document_version_id'], 'design_submission_document_version_unique');
        });

        Schema::create('design_package_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_package_submission_id');
            $table->foreign('design_package_submission_id', 'dreview_submission_fk')->references('id')->on('design_package_submissions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->index(['design_package_submission_id', 'status'], 'design_reviews_submission_status_index');
        });

        Schema::create('design_review_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_package_review_id');
            $table->foreign('design_package_review_id', 'dfinding_review_fk')->references('id')->on('design_package_reviews')->cascadeOnDelete();
            $table->foreignId('design_package_scope_item_id')->nullable();
            $table->foreign('design_package_scope_item_id', 'dfinding_scope_fk')->references('id')->on('design_package_scope_items')->nullOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity');
            $table->string('status')->default('open');
            $table->string('title');
            $table->text('description');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['design_package_review_id', 'status'], 'design_findings_review_status_index');
            $table->index(['severity', 'status'], 'design_findings_severity_status_index');
        });

        Schema::create('design_package_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_design_package_id');
            $table->foreign('project_design_package_id', 'drevision_package_fk')->references('id')->on('project_design_packages')->cascadeOnDelete();
            $table->foreignId('source_submission_id');
            $table->foreign('source_submission_id', 'drevision_source_fk')->references('id')->on('design_package_submissions')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status')->default('draft');
            $table->text('description')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_design_package_id', 'revision_number'], 'design_revisions_package_number_unique');
        });

        Schema::create('design_revision_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_package_revision_id');
            $table->foreign('design_package_revision_id', 'drevfinding_revision_fk')->references('id')->on('design_package_revisions')->cascadeOnDelete();
            $table->foreignId('design_review_finding_id');
            $table->foreign('design_review_finding_id', 'drevfinding_finding_fk')->references('id')->on('design_review_findings')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['design_package_revision_id', 'design_review_finding_id'], 'design_revision_finding_unique');
        });

        Schema::create('design_package_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_design_package_id');
            $table->foreign('project_design_package_id', 'dapproval_package_fk')->references('id')->on('project_design_packages')->cascadeOnDelete();
            $table->foreignId('design_package_submission_id');
            $table->foreign('design_package_submission_id', 'dapproval_submission_fk')->references('id')->on('design_package_submissions')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('project_design_package_id', 'design_approval_package_unique');
            $table->index(['project_design_package_id', 'design_package_submission_id'], 'design_approvals_package_submission_index');
        });
    }

    public function down(): void
    {
        foreach (['design_package_approvals', 'design_revision_findings', 'design_package_revisions', 'design_review_findings', 'design_package_reviews', 'design_package_submission_documents', 'design_package_submissions', 'design_package_activities', 'design_package_scope_items', 'design_package_assignments', 'project_design_packages', 'document_versions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
