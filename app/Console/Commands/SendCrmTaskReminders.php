<?php
namespace App\Console\Commands;
use App\Enums\CrmTaskStatus;
use App\Models\CrmFollowUpTask;
use App\Notifications\CrmTaskReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class SendCrmTaskReminders extends Command { protected $signature='crm:send-task-reminders'; protected $description='Send one in-app reminder for CRM tasks due within fifteen minutes'; public function handle():int { CrmFollowUpTask::withoutGlobalScopes()->whereIn('status',[CrmTaskStatus::Pending->value,CrmTaskStatus::InProgress->value])->whereNull('reminder_sent_at')->whereBetween('due_at',[now(),now()->addMinutes(15)])->orderBy('id')->each(function(CrmFollowUpTask $task):void { DB::transaction(function()use($task):void{$locked=CrmFollowUpTask::withoutGlobalScopes()->lockForUpdate()->find($task->id);if(!$locked||$locked->reminder_sent_at||!$locked->assignedTo)return;$locked->assignedTo->notify(new CrmTaskReminderNotification($locked));$locked->update(['reminder_sent_at'=>now()]);});}); return self::SUCCESS; } }
