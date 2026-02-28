<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

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
}
