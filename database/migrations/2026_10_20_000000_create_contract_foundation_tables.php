<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number', 50);
            $table->string('contract_type', 30);
            $table->string('status', 30)->default('draft');
            $table->foreignId('reservation_id')->nullable()->constrained('unit_reservations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('planned_unit_id')->nullable()->constrained('project_planned_units')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'contract_number'], 'contracts_company_number_unique');
            $table->index(['company_id', 'status']);
        });
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contract_type', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'contract_type', 'is_active']); });
        Schema::create('contract_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('body')->nullable();
            $table->json('clauses')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['contract_template_id', 'version_number'], 'contract_template_versions_template_version_unique'); });
        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 30)->default('draft');
            $table->foreignId('template_version_id')->nullable()->constrained('contract_template_versions')->nullOnDelete();
            $table->decimal('total_amount', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->json('unit_snapshot')->nullable();
            $table->json('reservation_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'version_number'], 'contract_versions_contract_version_unique');
            $table->index(['company_id', 'status']); });
        Schema::create('contract_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('role', 30);
            $table->decimal('share_percentage', 5, 2)->nullable();
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['contract_version_id', 'role']); });
        Schema::create('contract_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_party_id')->nullable()->constrained()->nullOnDelete();
            $table->string('term_type', 50);
            $table->unsignedInteger('position')->default(0);
            $table->string('title');
            $table->longText('text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['contract_version_id', 'position']); });
    }
    public function down(): void
    {
        Schema::dropIfExists('contract_terms');
        Schema::dropIfExists('contract_parties');
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('contract_template_versions');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('contracts');
    }
};
