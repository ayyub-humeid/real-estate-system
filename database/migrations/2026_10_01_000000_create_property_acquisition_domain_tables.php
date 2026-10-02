<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('national_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('property_acquisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->nullable();
            $table->string('type');
            $table->string('status')->default('draft');
            $table->date('acquisition_date')->nullable();
            $table->decimal('agreed_value', 18, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index('type');
            $table->index('acquisition_date');
        });

        Schema::create('acquisition_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_acquisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->decimal('share_percentage', 5, 2)->nullable();
            $table->decimal('allocated_value', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            // Explicit short name: MySQL limits identifiers to 64 characters.
            $table->unique(['property_acquisition_id', 'property_id'], 'acq_props_acq_property_unique');
        });

        Schema::create('acquisition_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_acquisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->decimal('share_percentage', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['property_acquisition_id', 'party_id']);
        });

        Schema::create('property_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->decimal('ownership_percentage', 5, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('acquisition_id')->nullable()->constrained('property_acquisitions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'end_date']);
            $table->index('party_id');
        });

        Schema::create('due_diligence_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_acquisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->index(['property_acquisition_id', 'status']);
        });

        Schema::create('due_diligence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('due_diligence_case_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('pending');
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['due_diligence_case_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_diligence_items'); Schema::dropIfExists('due_diligence_cases');
        Schema::dropIfExists('property_ownerships'); Schema::dropIfExists('acquisition_parties');
        Schema::dropIfExists('acquisition_properties'); Schema::dropIfExists('property_acquisitions');
        Schema::dropIfExists('parties');
    }
};
