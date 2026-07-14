<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentNotification extends Notification implements ShouldQueue
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
        return ['database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Receipt: ' . $this->payment->amount . ' - Unit ' . ($this->payment->lease->unit->unit_number ?? ''))
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We have successfully processed your payment.')
            ->line('Amount: ' . $this->payment->amount)
            ->line('Date: ' . $this->payment->payment_date)
            ->line('Thank you for your business!');
    }

    protected function isTenant(object $notifiable): bool
    {
        return $this->payment->lease &&
            $this->payment->lease->tenant &&
            $this->payment->lease->tenant->user_id === $notifiable->id;
    }

    public function toArray(object $notifiable): array
    {
        $isTenant = $this->isTenant($notifiable);
        $title = $isTenant ? 'Payment Successful' : 'Payment Received';
        $body = $isTenant
            ? "Your payment of {$this->payment->amount} has been successfully processed."
            : "A payment of {$this->payment->amount} was recorded for Unit " . ($this->payment->lease?->unit?->unit_number ?? '') . ' in Property : ' . ($this->payment->lease?->unit?->property?->name ?? '');

        $notification = \Filament\Notifications\Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-currency-dollar')
            ->iconColor('success');

        if (!$isTenant) {
            $notification->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->button()
                    ->url(\App\Filament\Resources\PaymentResource::getUrl('view', ['record' => $this->payment->id])),
            ]);
        }

        return $notification->getDatabaseMessage();
    }

    public function toBroadcast(object $notifiable): \Illuminate\Notifications\Messages\BroadcastMessage
    {
        $isTenant = $this->isTenant($notifiable);
        $title = $isTenant ? 'Payment Successful' : 'Payment Received';
        $body = $isTenant
            ? "Your payment of {$this->payment->amount} has been successfully processed."
            : "A payment of {$this->payment->amount} was recorded for Unit " . ($this->payment->lease?->unit?->unit_number ?? '') . ' in Property : ' . ($this->payment->lease?->unit?->property?->name ?? '');

        $notification = \Filament\Notifications\Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-currency-dollar')
            ->iconColor('success');

        if (!$isTenant) {
            $notification->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->button()
                    ->url(\App\Filament\Resources\PaymentResource::getUrl('view', ['record' => $this->payment->id])),
            ]);
        }

        return $notification->getBroadcastMessage();
    }

}
