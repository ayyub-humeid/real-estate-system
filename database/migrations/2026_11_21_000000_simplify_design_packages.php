<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the 11-table design/engineering workflow with two tables: a design
 * package and its numbered drawing versions, each approved or rejected.
 * Existing design data was demo data and is removed on purpose (Phase 05).
 */
return new class extends Migration
{
    private const PACKAGE_CLASS = 'App\\Models\\ProjectDesignPackage';
    private const ASSIGNMENT_CLASS = 'App\\Models\\DesignPackageAssignment';

    private const OBSOLETE_PERMISSIONS = [
        'assign_design_package', 'replace_design_assignment', 'submit_design_package', 'review_design_submission',
        'create_design_finding', 'waive_design_finding', 'request_design_revision', 'submit_design_revision',
        'approve_design_package', 'close_design_package',
        'view_any_design_package_assignment', 'view_design_package_assignment', 'create_design_package_assignment', 'update_design_package_assignment',
        'view_any_design_package_scope_item', 'view_design_package_scope_item', 'create_design_package_scope_item', 'update_design_package_scope_item', 'delete_design_package_scope_item',
        'view_any_design_package_activity', 'view_design_package_activity', 'create_design_package_activity', 'update_design_package_activity', 'delete_design_package_activity',
        'view_any_design_package_submission', 'view_design_package_submission', 'create_design_package_submission',
        'view_any_design_package_submission_document', 'view_design_package_submission_document', 'create_design_package_submission_document',
        'view_any_design_package_review', 'view_design_package_review', 'create_design_package_review',
        'view_any_design_review_finding', 'view_design_review_finding', 'create_design_review_finding',
        'view_any_design_package_revision', 'view_design_package_revision', 'create_design_package_revision',
        'view_any_design_revision_finding', 'view_design_revision_finding', 'create_design_revision_finding',
        'view_any_design_package_approval', 'view_design_package_approval', 'create_design_package_approval',
    ];

    public function up(): void
    {
        foreach ([
            'design_package_approvals', 'design_revision_findings', 'design_package_revisions',
            'design_review_findings', 'design_package_reviews', 'design_package_submission_documents',
            'design_package_submissions', 'design_package_activities', 'design_package_scope_items',
            'design_package_assignments',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $documentIds = DB::table('documents')->where('documentable_type', self::PACKAGE_CLASS)->pluck('id');
        if ($documentIds->isNotEmpty()) {
            DB::table('document_versions')->whereIn('document_id', $documentIds)->delete();
            DB::table('documents')->whereIn('id', $documentIds)->delete();
        }

        if (Schema::hasTable('financial_commitments')) {
            DB::table('financial_commitments')
                ->whereIn('source_type', [self::PACKAGE_CLASS, self::ASSIGNMENT_CLASS])
                ->update(['source_type' => null, 'source_id' => null]);
        }

        Schema::dropIfExists('project_design_packages');

        Schema::create('project_design_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('discipline')->nullable();
            $table->foreignId('engineering_office_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('no_drawings');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'status'], 'design_packages_project_status_index');
        });

        Schema::create('design_package_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_design_package_id');
            $table->foreign('project_design_package_id', 'dversion_package_fk')->references('id')->on('project_design_packages')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('document_version_id');
            $table->foreign('document_version_id', 'dversion_file_fk')->references('id')->on('document_versions')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision')->default('pending');
            $table->text('decision_comment')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['project_design_package_id', 'version_number'], 'dversion_package_number_unique');
            $table->index(['project_design_package_id', 'decision'], 'dversion_package_decision_index');
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', self::OBSOLETE_PERMISSIONS)->delete();
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /** The removed workflow is not restored; rolling back only removes the new tables. */
    public function down(): void
    {
        Schema::dropIfExists('design_package_versions');
        Schema::dropIfExists('project_design_packages');
    }
};
