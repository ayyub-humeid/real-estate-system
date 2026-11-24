<?php
namespace App\Notifications;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class ProjectWorkflowNotification extends Notification
{
    use Queueable;
    public function __construct(private string $title, private string $body, private ?int $projectId = null, private ?string $url = null)
    {
    }
    public function via(object $notifiable): array
    {
        return ['database'];
    }
    public function toArray(object $notifiable): array
    {
        $message = FilamentNotification::make()->title($this->title)->body($this->body);

        if ($this->url) {
            $message->actions([Action::make('open')->label(__('Open'))->url($this->url)->markAsRead()]);
        }

        return array_merge($message->getDatabaseMessage(), ['project_id' => $this->projectId]);
    }
}
