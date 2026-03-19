<?php
// app/Observers/PaymentObserver.php

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    public function saved(Payment $payment): void
    {
        $this->updateLeaseBalanceAndStatus($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->updateLeaseBalanceAndStatus($payment);
    }

    protected function updateLeaseBalanceAndStatus(Payment $payment): void
    {
        $lease = $payment->lease;
        
        // إذا تم حذف العقد أو غير موجود، نوقف التنفيذ
        if (!$lease) return;

        // 1️⃣ حساب الرصيد المتبقي (Outstanding Balance)
        if ($lease->payment_frequency === 'monthly') {
            $totalRemaining = $lease->payments()
                ->whereNotIn('status', ['cancelled'])
                ->sum('remaining_amount');
        } else {
            $totalPaid = $lease->payments()
                ->whereNotIn('status', ['cancelled'])
                ->sum('paid_amount');
            // تأكد إن rent_amount موجودة عشان ما يعطي سالب بالغلط
            $totalRemaining = max(0, $lease->rent_amount - $totalPaid);
        }

        // 2️⃣ تحديد حالة العقد تلقائياً (Auto-update Lease Status)
        $newStatus = $lease->status; // الحالة الافتراضية هي الحالة الحالية

        $hasOverduePayments = $lease->payments()->where('status', 'overdue')->exists();

        if ($hasOverduePayments) {
            // إذا في دفعة متأخرة، العقد يصبح متأخر/متعثر
            $newStatus = 'defaulted'; // أو 'overdue' حسب المسميات في موديل العقد عندك
        } elseif ($totalRemaining <= 0) {
            $newStatus = 'paid'; // أو 'active' أو 'completed'
        } else {
            $newStatus = 'active'; 
        }

        $lease->forceFill([
            'outstanding_balance' => $totalRemaining,
            'status' => $newStatus
        ])->saveQuietly();
    }
}