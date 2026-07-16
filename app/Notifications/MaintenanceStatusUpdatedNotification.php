<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MaintenanceStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public MaintenanceRequest $maintenanceRequest)
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $statusLabel = str_replace('_', ' ', $this->maintenanceRequest->status);
        $statusLabel = ucwords($statusLabel);

        return [
            'title' => 'Maintenance Request Updated',
            'body' => "Your request \"{$this->maintenanceRequest->title}\" status is now {$statusLabel}.",
            'status' => $this->maintenanceRequest->status,
            'request_id' => $this->maintenanceRequest->id,
            'url' => '/tenant/maintenance',
        ];
    }
}
