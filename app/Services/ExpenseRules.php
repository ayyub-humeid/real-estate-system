<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Party;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Invariants of an expense, applied on every save. Only changed values are
 * checked, so older records stay editable.
 */
class ExpenseRules
{
    /** What may still change once an expense is paid: paperwork, or its cancellation. */
    private const EDITABLE_WHEN_PAID = ['status', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'receipt_path', 'reference_number', 'notes', 'updated_at'];

    private const EDITABLE_WHEN_CANCELLED = ['receipt_path', 'notes', 'updated_at'];

    public function apply(Expense $expense): void
    {
        $this->guardHistory($expense);
        $this->guardValues($expense);
        $this->guardLinks($expense);
        $this->applySalaryRules($expense);
        $this->applyPaymentDate($expense);
    }

    private function guardHistory(Expense $expense): void
    {
        if (! $expense->exists) {
            return;
        }

        $from = ExpenseStatus::tryFrom((string) $expense->getOriginal('status'));

        if ($expense->isDirty('status')) {
            $to = ExpenseStatus::tryFrom((string) $expense->status);

            if ($from && (! $to || ! $from->canTransitionTo($to))) {
                $this->invalid('status', __('expense_domain.errors.status_change', [
                    'from' => $from->getLabel(),
                    'to' => ExpenseStatus::labelFor($expense->status),
                ]));
            }
        }

        $editable = match ($from) {
            ExpenseStatus::Paid => self::EDITABLE_WHEN_PAID,
            ExpenseStatus::Cancelled => self::EDITABLE_WHEN_CANCELLED,
            default => null,
        };

        if ($editable !== null && array_diff(array_keys($expense->getDirty()), $editable) !== []) {
            $this->invalid('status', __('expense_domain.errors.locked', ['status' => $from->getLabel()]));
        }
    }

    private function guardValues(Expense $expense): void
    {
        if ($expense->isDirty('amount') && (float) $expense->amount <= 0) {
            $this->invalid('amount', __('expense_domain.errors.amount_positive'));
        }

        $allowed = [
            'category' => ExpenseCategory::class,
            'status' => ExpenseStatus::class,
            'currency' => Currency::class,
            'payment_method' => PaymentMethod::class,
        ];

        foreach ($allowed as $field => $enum) {
            $value = $expense->getAttribute($field);

            if ($expense->isDirty($field) && filled($value) && ! $enum::tryFrom((string) $value)) {
                $this->invalid($field, __('expense_domain.errors.unknown_value', ['field' => __('expense_domain.fields.'.$field)]));
            }
        }
    }

    /** The unit, property, employee and payee must all belong to the expense's company. */
    private function guardLinks(Expense $expense): void
    {
        if ($expense->unit_id && $expense->isDirty(['unit_id', 'property_id', 'company_id'])) {
            $unit = Unit::withoutGlobalScopes()->find($expense->unit_id);
            $this->assertSameCompany($expense, $unit, 'unit_id');

            if ($expense->property_id && $unit->property_id && (int) $unit->property_id !== (int) $expense->property_id) {
                $this->invalid('unit_id', __('expense_domain.errors.unit_outside_property'));
            }

            $expense->property_id ??= $unit->property_id;
        }

        $links = ['property_id' => Property::class, 'employee_id' => Employee::class, 'party_id' => Party::class];

        foreach ($links as $field => $model) {
            if ($expense->getAttribute($field) && $expense->isDirty([$field, 'company_id'])) {
                $this->assertSameCompany($expense, $model::withoutGlobalScopes()->find($expense->getAttribute($field)), $field);
            }
        }
    }

    private function applySalaryRules(Expense $expense): void
    {
        if ($expense->isLocked() || ! $expense->isDirty(['category', 'employee_id', 'salary_month'])) {
            return;
        }

        if (! ExpenseCategory::tryFrom((string) $expense->category)?->requiresEmployee()) {
            $expense->employee_id = null;
            $expense->salary_month = null;

            return;
        }

        if (! $expense->employee_id) {
            return;
        }

        $month = Carbon::parse($expense->salary_month ?? $expense->expense_date ?? now())->startOfMonth();
        $expense->salary_month = $month->toDateString();

        $duplicate = Expense::withoutGlobalScopes()
            ->where('employee_id', $expense->employee_id)
            ->whereDate('salary_month', $month->toDateString())
            ->where('status', '!=', ExpenseStatus::Cancelled->value)
            ->when($expense->exists, fn ($query) => $query->whereKeyNot($expense->getKey()))
            ->exists();

        if ($duplicate) {
            $this->invalid('salary_month', __('expense_domain.errors.salary_already_recorded', ['month' => $month->format('m/Y')]));
        }
    }

    private function applyPaymentDate(Expense $expense): void
    {
        $status = (string) ($expense->status ?? ExpenseStatus::Pending->value);

        if ($status === ExpenseStatus::Pending->value) {
            $expense->paid_at = null;
            $expense->payment_method = null;

            return;
        }

        if ($status !== ExpenseStatus::Paid->value) {
            return;
        }

        $today = now()->startOfDay();

        if (! $expense->paid_at) {
            $dated = Carbon::parse($expense->expense_date ?? $today);
            $expense->paid_at = ($dated->greaterThan($today) ? $today : $dated)->toDateString();
        } elseif ($expense->isDirty('paid_at') && $expense->paid_at->greaterThan($today)) {
            $this->invalid('paid_at', __('expense_domain.errors.paid_in_future'));
        }
    }

    private function assertSameCompany(Expense $expense, ?object $related, string $field): void
    {
        if (! $related || (int) $related->company_id !== (int) $expense->company_id) {
            $this->invalid($field, __('expense_domain.errors.other_company', ['field' => __('expense_domain.fields.'.$field)]));
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
