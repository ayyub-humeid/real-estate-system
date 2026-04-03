<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public \App\Models\MaintenanceRequest $maintenanceRequest)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('New Maintenance Request: ' . $this->maintenanceRequest->unit->property->name)
                    ->greeting('Hello ' . $notifiable->name . '!')
                    ->line('A new maintenance request has been submitted for Unit ' . $this->maintenanceRequest->unit->name)
                    ->line('Description: ' . $this->maintenanceRequest->description)
                    ->action('View Request', \App\Filament\Resources\MaintenanceRequestResource::getUrl('view', ['record' => $this->maintenanceRequest->id]))
                    ->line('Thank you for using our system!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('New Maintenance Request')
            ->body("Unit {$this->maintenanceRequest->unit->name} requires attention.")
            ->icon('heroicon-o-wrench-screwdriver')
            ->iconColor('warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->button()
                    ->url(fn () => \App\Filament\Resources\MaintenanceRequestResource::getUrl('view', ['record' => $this->maintenanceRequest->id])),
            ])
            ->getDatabaseMessage();
    }
}
