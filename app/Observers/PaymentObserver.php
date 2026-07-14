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
        if (!$payment->lease || !$payment->lease->tenant) {
            return;
        }

        // Notify Tenant (if user exists)
        $tenantUser = $payment->lease->tenant->user;
        if ($tenantUser) {
            $tenantUser->notify(new \App\Notifications\PaymentNotification($payment));
        }

        // Notify Financial Managers in same company
        $managers = \App\Models\User::where('company_id', $payment->company_id)
            // ->role('financial_manager')
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new \App\Notifications\PaymentNotification($payment));
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