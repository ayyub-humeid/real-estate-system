<?php
namespace App\Notifications;
use App\Models\CrmFollowUpTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class CrmTaskReminderNotification extends Notification
{
    use Queueable;
    public function __construct(private CrmFollowUpTask $task)
    {
    }
    public function via(object $notifiable): array
    {
        return ['database'];
    }
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('crm.tasks.navigation'),
            'body' => $this->task->title,
            'crm_follow_up_task_id' => $this->task->id
        ];
    }
}
