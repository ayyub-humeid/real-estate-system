<?php
// app/Observers/PaymentObserver.php

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        $this->updateLeaseBalance($payment);
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        $this->updateLeaseBalance($payment);
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        $this->updateLeaseBalance($payment);
    }

    /**
     * Update the lease outstanding balance
     */
    protected function updateLeaseBalance(Payment $payment): void
    {
        if (!$payment->lease) {
            return;
        }

        // Calculate total remaining amount from all payments for this lease
        $totalRemaining = $payment->lease->payments()
            ->whereNotIn('status', ['cancelled'])
            ->sum('remaining_amount');

        // Update the lease outstanding balance
        $payment->lease->update([
            'outstanding_balance' => $totalRemaining
        ]);
    }
}