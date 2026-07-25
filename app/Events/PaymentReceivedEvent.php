<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Payment $payment,
        public int $userId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.received';
    }

    public function broadcastWith(): array
    {
        $amount = number_format($this->payment->amount, 2);
        $unitNumber = $this->payment->lease?->unit?->unit_number ?? '';
        $propertyName = $this->payment->lease?->unit?->property?->name ?? '';

        return [
            'payment_id' => $this->payment->id,
            'title' => 'Payment Received',
            'body' => "A payment of {$amount} was recorded for Unit {$unitNumber} in Property: {$propertyName}",
            'url' => \App\Filament\Resources\PaymentResource::getUrl('view', ['record' => $this->payment->id]),
        ];
    }
}
