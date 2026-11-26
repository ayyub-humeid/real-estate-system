<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The status changes of an expense. Linking an expense to a payment record later
 * belongs in these two methods, so every screen picks it up at once.
 */
class ExpenseService
{
    /** @param array{payment_method?: ?string, paid_at?: ?string, reference_number?: ?string} $data */
    public function markPaid(User $actor, Expense $expense, array $data): Expense
    {
        $this->authorize($actor, 'pay', $expense);

        return DB::transaction(function () use ($expense, $data): Expense {
            $locked = $this->lock($expense);
            $this->assertCanMoveTo($locked, ExpenseStatus::Paid);

            if (! PaymentMethod::tryFrom((string) ($data['payment_method'] ?? ''))) {
                $this->invalid('payment_method', __('expense_domain.errors.payment_method_required'));
            }

            if (blank($data['paid_at'] ?? null)) {
                $this->invalid('paid_at', __('expense_domain.errors.paid_at_required'));
            }

            $locked->update([
                'status' => ExpenseStatus::Paid->value,
                'payment_method' => $data['payment_method'],
                'paid_at' => $data['paid_at'],
                'reference_number' => $data['reference_number'] ?? $locked->reference_number,
            ]);

            return $locked->refresh();
        });
    }

    /** Cancels a pending expense, or reverses a paid one. The record and its payment details are kept. */
    public function cancel(User $actor, Expense $expense, string $reason): Expense
    {
        $this->authorize($actor, 'cancel', $expense);

        return DB::transaction(function () use ($actor, $expense, $reason): Expense {
            $locked = $this->lock($expense);
            $this->assertCanMoveTo($locked, ExpenseStatus::Cancelled);

            if (blank($reason)) {
                $this->invalid('reason', __('expense_domain.errors.reason_required'));
            }

            $locked->forceFill([
                'status' => ExpenseStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            return $locked->refresh();
        });
    }

    private function lock(Expense $expense): Expense
    {
        return Expense::withoutGlobalScopes()->lockForUpdate()->findOrFail($expense->getKey());
    }

    private function assertCanMoveTo(Expense $expense, ExpenseStatus $to): void
    {
        if (! $expense->canMoveTo($to)) {
            $this->invalid('status', __('expense_domain.errors.status_change', [
                'from' => ExpenseStatus::labelFor($expense->status),
                'to' => $to->getLabel(),
            ]));
        }
    }

    private function authorize(User $actor, string $ability, Expense $expense): void
    {
        if (! $actor->can($ability, $expense)) {
            throw new AuthorizationException;
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
