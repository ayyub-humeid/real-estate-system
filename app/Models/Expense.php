<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use \App\Traits\HasCompany;
    use \App\Models\Concerns\PreventsDeletionWithHistory;

    protected function deletionChecks(): array
    {
        return [
            'paid_expense' => $this->getOriginal('status') === self::STATUS_PAID,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                // Try to get from property first
                if ($model->property_id) {
                    $property = Property::find($model->property_id);
                    if ($property) {
                        $model->company_id = $property->company_id;
                    }
                }
                // Fallback: derive from unit -> property
                if (empty($model->company_id) && $model->unit_id) {
                    $unit = Unit::with('property')->find($model->unit_id);
                    if ($unit && $unit->property) {
                        $model->company_id = $unit->property->company_id;
                    }
                }
            }
        });
    }

    protected $fillable = [
        'company_id',
        'property_id',
        'unit_id',
        'created_by',
        'title',
        'description',
        'category',
        'amount',
        'currency',
        'status',
        'expense_date',
        'paid_at',
        'payment_method',
        'receipt_path',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'paid_at'      => 'date',
        'amount'       => 'decimal:2',
    ];

    // Category Constants
    const CATEGORIES = [
        'maintenance' => 'Maintenance',
        'utilities'   => 'Utilities',
        'salaries'    => 'Salaries',
        'insurance'   => 'Insurance',
        'taxes'       => 'Taxes',
        'marketing'   => 'Marketing',
        'other'       => 'Other',
    ];

    // Status Constants
    const STATUS_PENDING   = 'pending';
    const STATUS_PAID      = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Scopes ---

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
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
