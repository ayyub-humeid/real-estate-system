<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaseExpiringNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public \App\Models\Lease $lease)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Lease Expiring Soon: ' . $this->lease->unit->name)
                    ->greeting('Hello ' . $notifiable->name . '!')
                    ->line('The lease for Unit ' . $this->lease->unit->name . ' is expiring on ' . \Carbon\Carbon::parse($this->lease->end_date)->format('Y-m-d'))
                    ->action('View Lease Details', url('/admin/leases/' . $this->lease->id))
                    ->line('Please take the necessary actions for renewal or termination.');
    }

    public function toArray(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('Lease Expiring Soon')
            ->body("Lease for Unit {$this->lease->unit->name} ends in 30 days.")
            ->icon('heroicon-o-clock')
            ->iconColor('danger')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->button()
                    ->url(fn () => url('/admin/leases/' . $this->lease->id)),
            ])
            ->getDatabaseMessage();
    }
}
