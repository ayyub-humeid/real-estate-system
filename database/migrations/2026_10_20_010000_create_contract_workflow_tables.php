<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->unique(['contract_version_id', 'reviewed_by'], 'contract_approval_version_reviewer_unique');
        });
        Schema::create('contract_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_party_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained()->restrictOnDelete();
            $table->string('signatory_name');
            $table->string('signing_method', 32);
            $table->string('file_sha256', 64);
            $table->json('evidence')->nullable();
            $table->timestamp('signed_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['contract_version_id', 'contract_party_id'], 'contract_signature_version_party_unique');
        });
        Schema::create('contract_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('action', 48);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['contract_id', 'changed_at']);
        });
        Schema::create('contract_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('base_contract_version_id')->constrained('contract_versions')->restrictOnDelete();
            $table->foreignId('resulting_contract_version_id')->nullable()->constrained('contract_versions')->nullOnDelete();
            $table->string('amendment_type', 32);
            $table->string('status', 24)->default('draft');
            $table->text('reason');
            $table->date('effective_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'contract_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('contract_status_histories');
        Schema::dropIfExists('contract_signatures');
        Schema::dropIfExists('contract_approvals');
    }
};
