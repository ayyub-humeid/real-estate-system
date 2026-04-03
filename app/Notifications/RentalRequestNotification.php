<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RentalRequestNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public \App\Models\RentalRequest $rentalRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('New Rental Request for Unit: ' . $this->rentalRequest->unit->name)
                    ->greeting('Hello ' . $notifiable->name . '!')
                    ->line('A new rental request has been received from ' . $this->rentalRequest->name)
                    ->line('Email: ' . $this->rentalRequest->email)
                    ->action('View Request', url('/admin/rental-requests/' . $this->rentalRequest->id))
                    ->line('Thank you for using our system!');
    }

    public function toArray(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('New Rental Inquiry')
            ->body("New request from {$this->rentalRequest->name} for Unit {$this->rentalRequest->unit->name}")
            ->icon('heroicon-o-home')
            ->iconColor('success')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->button()
                    ->url(fn () => url('/admin/rental-requests/' . $this->rentalRequest->id)),
            ])
            ->getDatabaseMessage();
    }
}
