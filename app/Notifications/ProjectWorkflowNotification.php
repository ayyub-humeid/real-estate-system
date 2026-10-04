<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class ProjectWorkflowNotification extends Notification
{
    use Queueable;
    public function __construct(private string $title, private string $body, private ?int $projectId = null)
    {
    }
    public function via(object $notifiable): array
    {
        return ['database'];
    }
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'project_id' => $this->projectId];
    }
}
