<?php

namespace App\Notifications;

use App\Models\ContractFinancialEvent;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ContractFinancialNotification extends Notification
{
    public function __construct(private readonly ContractFinancialEvent $event)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }
        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return $this->message()->getDatabaseMessage();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return $this->message()->getBroadcastMessage();
    }

    private function message(): FilamentNotification
    {
        $contractNumber = $this->event->contract?->contract_number ?? '';
        $title = __($this->notificationKey($this->event->event_type));
        $body = $contractNumber !== '' ? __('contract_finance.fields.contract').': '.$contractNumber : $title;

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon($this->event->event_type === 'installment_overdue' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-banknotes')
            ->iconColor($this->event->event_type === 'installment_overdue' ? 'danger' : 'primary')
            ->actions([
                Action::make('view')
                    ->label(__('contract_finance.fields.view'))
                    ->button()
                    ->url(\App\Filament\Resources\ContractResource::getUrl('view', ['record' => $this->event->contract_id])),
            ]);
    }

    private function notificationKey(string $eventType): string
    {
        return match ($eventType) {
            'installment_upcoming' => 'contract_finance.notifications.upcoming',
            'installment_due' => 'contract_finance.notifications.due',
            'installment_overdue' => 'contract_finance.notifications.overdue',
            'contract_settled' => 'contract_finance.notifications.settled',
            default => 'contract_finance.notifications.'.$eventType,
        };
    }
}
