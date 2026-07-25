<?php
// app/Observers/PaymentObserver.php

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->updateLeaseBalanceAndStatus($payment);

        if (in_array($payment->status, ['paid', 'partial'])) {
            $this->sendNotifications($payment);
        }
    }

    public function updated(Payment $payment): void
    {
        $this->updateLeaseBalanceAndStatus($payment);

        // Only send if the status JUST changed to paid or partial
        if ($payment->wasChanged('status') && in_array($payment->status, ['paid', 'partial'])) {
            $this->sendNotifications($payment);
        }
    }

    protected function sendNotifications(Payment $payment): void
    {
        \Illuminate\Support\Facades\DB::afterCommit(function () use ($payment) {
            // Increase timeout for synchronous notifications on hosting
            set_time_limit(120);

            if (!$payment->lease || !$payment->lease->tenant) {
                return;
            }

            // Notify Tenant (if user exists)
            $tenantUser = $payment->lease->tenant->user;
            if ($tenantUser) {
                $tenantUser->notifyNow(new \App\Notifications\PaymentNotification($payment));
            }

            // Notify Financial Managers in same company
            $managers = \App\Models\User::where('company_id', $payment->company_id)
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['company_admin']);
                })
                ->get();

            foreach ($managers as $manager) {
                // 1. Save database notification record (no broadcast since we updated via())
                $manager->notifyNow(new \App\Notifications\PaymentNotification($payment));

                // 2. Broadcast the custom payment received event for real-time toast
                \App\Events\PaymentReceivedEvent::dispatch($payment, $manager->id);

                // 3. Trigger Filament's notification bell refresh synchronously
                $this->broadcastDatabaseNotificationsSent($manager);
            }
        });
    }

    /**
     * Broadcast the 'database-notifications.sent' event synchronously
     * to trigger Filament's notification bell refresh without needing queue:work.
     */
    protected function broadcastDatabaseNotificationsSent(\App\Models\User $user): void
    {
        $userClass = str_replace('\\', '.', get_class($user));
        $channelName = "private-{$userClass}.{$user->getKey()}";

        try {
            app(\Illuminate\Broadcasting\BroadcastManager::class)
                ->connection()
                ->broadcast(
                    [$channelName],
                    'database-notifications.sent',
                    []
                );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to broadcast database-notifications.sent: ' . $e->getMessage());
        }
    }

    public function deleted(Payment $payment): void
    {
        $this->updateLeaseBalanceAndStatus($payment);
    }

    protected function updateLeaseBalanceAndStatus(Payment $payment): void
    {
        $lease = $payment->lease;

        if (!$lease)
            return;

        // Outstanding balance based on explicit payment amounts
        $totalExpected = (float) $lease->payments()
            ->whereNotIn('status', ['cancelled'])
            ->sum('amount');

        $totalPaid = (float) $lease->payments()
            ->whereNotIn('status', ['cancelled'])
            ->sum('paid_amount');

        $totalRemaining = max(0, $totalExpected - $totalPaid);

        // Determine new lease status
        // NOTE: Overdue payments don't change the lease status itself —
        // that is handled separately (e.g. via markAsOverdue on individual payments).
        // We only update outstanding_balance here; lease status should remain 'active'
        // unless fully paid or already in a terminal state (draft/terminated/renewed).
        $terminalStatuses = ['terminated', 'renewed', 'expired'];

        if (in_array($lease->status, $terminalStatuses)) {
            $newStatus = $lease->status; // Preserve terminal states, never overwrite them
        } elseif ($totalRemaining <= 0 && $totalExpected > 0) {
            $newStatus = 'active'; // Lease is active even when fully paid
        } elseif ($lease->status === 'draft') {
            $newStatus = 'draft';
        } else {
            $newStatus = 'active';
        }

        $lease->forceFill([
            'status' => $newStatus,
        ])->saveQuietly();
    }
}