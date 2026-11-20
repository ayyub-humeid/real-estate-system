<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->date('ended_at')->nullable()->after('status');
            $table->string('end_reason', 500)->nullable()->after('ended_at');
            $table->foreignId('ended_by')->nullable()->after('end_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ended_by');
            $table->dropColumn(['ended_at', 'end_reason']);
        });
    }
};
