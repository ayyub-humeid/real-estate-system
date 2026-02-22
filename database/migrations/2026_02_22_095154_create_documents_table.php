<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
             // Polymorphic Relationship
            $table->morphs('documentable'); // Creates documentable_id and documentable_type
            
            // Document Details
            $table->string('title');
            $table->string('file_name'); // Original file name
            $table->string('file_path'); // Storage path
            $table->string('file_type')->nullable(); // MIME type (application/pdf, image/jpeg)
            $table->unsignedBigInteger('file_size')->nullable(); // Size in bytes
            $table->string('extension', 10)->nullable(); // pdf, jpg, docx, etc.
            
            // Categorization
            $table->enum('document_type', [
                'contract', 
                'receipt', 
                'invoice', 
                'id_document', 
                'proof_of_income',
                'maintenance_report',
                'inspection_report',
                'other'
            ])->default('other');
            
            // Metadata
            $table->text('description')->nullable();
            $table->date('document_date')->nullable(); // Date on the document
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            // $table->index(['documentable_type', 'documentable_id']);
            $table->index('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
