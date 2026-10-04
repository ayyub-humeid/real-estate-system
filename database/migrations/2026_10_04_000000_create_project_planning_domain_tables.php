<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('project_type');
            $table->string('status')->default('planning');
            $table->date('start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('project_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('role')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('attached_at');
            $table->timestamp('detached_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'property_id']);
            $table->index(['company_id', 'property_id']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->index(['company_id', 'user_id']);
        });

        Schema::create('project_buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('building_type')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('planned');
            $table->timestamps();
            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('project_building_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_building_id')->constrained()->cascadeOnDelete();
            $table->integer('floor_number');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['project_building_id', 'floor_number']);
        });

        Schema::create('project_planned_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_building_floor_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('unit_type');
            $table->decimal('planned_area', 12, 2)->nullable();
            $table->string('status')->default('planned');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['project_building_floor_id', 'code']);
            $table->index(['project_building_floor_id', 'status']);
        });

        Schema::create('planned_unit_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_planned_unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('value');
            $table->string('unit')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('project_planned_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_unit_specifications');
        Schema::dropIfExists('project_planned_units');
        Schema::dropIfExists('project_building_floors');
        Schema::dropIfExists('project_buildings');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_properties');
        Schema::dropIfExists('projects');
    }
};
