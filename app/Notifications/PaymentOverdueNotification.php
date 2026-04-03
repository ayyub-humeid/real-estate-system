<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public \App\Models\Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Urgent: Payment Overdue for Unit ' . $this->payment->lease->unit->name)
                    ->greeting('Hello ' . $notifiable->name . '!')
                    ->line('Our records show that your payment of ' . $this->payment->amount . ' was due on ' . \Carbon\Carbon::parse($this->payment->due_date)->format('Y-m-d') . ' and is now overdue.')
                    ->action('Pay Now', \App\Filament\Resources\PaymentResource::getUrl('view', ['record' => $this->payment->id]))
                    ->line('Please settle this balance immediately to avoid any late fees.');
    }

    public function toArray(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('Payment Overdue')
            ->body("A payment of {$this->payment->amount} for Unit {$this->payment->lease->unit->name} is overdue.")
            ->icon('heroicon-o-exclamation-circle')
            ->iconColor('danger')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->button()
                    ->url(fn () => \App\Filament\Resources\PaymentResource::getUrl('view', ['record' => $this->payment->id])),
            ])
            ->getDatabaseMessage();
    }
}
