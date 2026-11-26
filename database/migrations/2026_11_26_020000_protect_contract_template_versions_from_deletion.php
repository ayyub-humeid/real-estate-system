<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contract PDF is printed from its template version's legal text. The
     * link was cleared when a template version was deleted, which would leave a
     * signed contract unable to be reprinted and its legal body unaccounted for.
     * A template version a contract relies on must now be impossible to delete.
     */
    public function up(): void
    {
        Schema::table('contract_versions', function (Blueprint $table): void {
            $table->dropForeign(['template_version_id']);
            $table->foreign('template_version_id')
                ->references('id')
                ->on('contract_template_versions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_versions', function (Blueprint $table): void {
            $table->dropForeign(['template_version_id']);
            $table->foreign('template_version_id')
                ->references('id')
                ->on('contract_template_versions')
                ->nullOnDelete();
        });
    }
};
