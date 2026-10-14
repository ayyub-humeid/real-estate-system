<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_follow_up_tasks', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable()->after('cancelled_at');
            $table->index(['company_id', 'status', 'due_at', 'reminder_sent_at'], 'crm_task_reminder_scan_idx'); }); }
    public function down(): void
    {
        Schema::table('crm_follow_up_tasks', function (Blueprint $table): void {
            $table->dropIndex('crm_task_reminder_scan_idx');
            $table->dropColumn('reminder_sent_at'); }); }
};
