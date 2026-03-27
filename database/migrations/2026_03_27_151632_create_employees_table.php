<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Identity
            $table->string('employee_id')->unique()->nullable(); // EMP-001
            $table->string('avatar')->nullable();

            // Position
            $table->string('position')->nullable();              // Property Manager, Accountant, etc.
            $table->string('department')->nullable();             // Operations, Finance, Maintenance
            $table->date('hire_date')->nullable();
            $table->decimal('salary', 12, 2)->nullable();

            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'on_leave', 'terminated'])->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
