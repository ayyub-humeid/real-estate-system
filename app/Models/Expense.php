<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Services\ExpenseRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use \App\Traits\HasCompany;
    use \App\Models\Concerns\PreventsDeletionWithHistory;

    /** Only an expense that never reached the books may be deleted. */
    protected function deletionChecks(): array
    {
        return [
            'paid_expense' => $this->getOriginal('status') === ExpenseStatus::Paid->value || $this->getOriginal('paid_at') !== null,
            'cancelled_expense' => $this->getOriginal('status') === ExpenseStatus::Cancelled->value,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_by ??= auth()->id();
        });

        // Every write path (panel, service, import, seeder) obeys the same rules.
        static::saving(function (self $expense): void {
            // "saving" runs before "creating", so the company must be known here
            // for the rules to compare the linked records against it.
            if (! $expense->exists && empty($expense->company_id)) {
                $expense->company_id = $expense->resolveCompanyId();
            }

            app(ExpenseRules::class)->apply($expense);
        });
    }

    /** A company user records for their own company; otherwise it follows the property or unit. */
    private function resolveCompanyId(): ?int
    {
        $user = auth()->user();

        if ($user && ! $user->isSuperAdmin()) {
            return $user->company_id;
        }

        if ($this->property_id) {
            return Property::find($this->property_id)?->company_id;
        }

        return $this->unit_id ? Unit::find($this->unit_id)?->company_id : null;
    }

    protected $fillable = [
        'company_id',
        'property_id',
        'unit_id',
        'employee_id',
        'party_id',
        'created_by',
        'title',
        'description',
        'category',
        'amount',
        'currency',
        'status',
        'expense_date',
        'salary_month',
        'paid_at',
        'payment_method',
        'receipt_path',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'salary_month' => 'date',
        'paid_at'      => 'date',
        'cancelled_at' => 'datetime',
        'amount'       => 'decimal:2',
    ];

    /** Paid and cancelled expenses are history: their figures no longer change. */
    public function isLocked(): bool
    {
        return $this->exists
            && in_array($this->getOriginal('status'), [ExpenseStatus::Paid->value, ExpenseStatus::Cancelled->value], true);
    }

    public function canMoveTo(ExpenseStatus $to): bool
    {
        return ExpenseStatus::tryFrom((string) $this->status)?->canTransitionTo($to) ?? false;
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** The supplier or person being paid, when it is not an employee. */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Expenses carry no project of their own; they reach it through the unit or the property. */
    public function effectiveProject(): ?Project
    {
        return $this->unit?->effectiveProject() ?? $this->property?->currentProject();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Scopes ---

    public function scopePending($query)
    {
        return $query->where('status', ExpenseStatus::Pending->value);
    }

    public function scopePaid($query)
    {
        return $query->where('status', ExpenseStatus::Paid->value);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('expense_date', '>=', now()->subDays($days));
    }
}
