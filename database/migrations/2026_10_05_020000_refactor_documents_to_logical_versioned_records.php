<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documents are logical records. Exact files belong only to document_versions.
     * Legacy attachment rows are intentionally discarded: they were confirmed as
     * dummy data and are not part of the versioned design domain.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['uploaded_by']);
            $table->dropIndex('documents_document_type_index');
            $table->dropColumn([
                'file_name',
                'file_path',
                'file_type',
                'file_size',
                'extension',
                'document_type',
                'document_date',
                'uploaded_by',
            ]);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('extension', 10)->nullable();
            $table->string('document_type')->nullable();
            $table->date('document_date')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('document_type');
        });
    }
};
