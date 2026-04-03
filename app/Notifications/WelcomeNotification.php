<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Welcome to Real Estate SaaS')
                    ->greeting('Hello ' . $notifiable->name . '!')
                    ->line('Your account has been successfully created.')
                    ->action('Login Now', url('/admin/login'))
                    ->line('Thank you for joining us!');
    }

    public function toArray(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('Welcome Aboard!')
            ->body("We are glad to have you in our system.")
            ->icon('heroicon-o-sparkles')
            ->iconColor('success')
            ->getDatabaseMessage();
    }
}
