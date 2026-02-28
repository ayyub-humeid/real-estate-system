<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalRequest extends Model
{
    protected $fillable = [
        'company_id',
        'tenant_id',
        'unit_id',
        'title',
        'description',
        'status',
        'priority',
        'preferred_type',
        'max_budget',
        'desired_move_in',
        'duration_months',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'desired_move_in' => 'date',
        'reviewed_at'     => 'datetime',
        'max_budget'      => 'decimal:2',
        'duration_months' => 'integer',
    ];

    // Status Constants
    const STATUS_PENDING   = 'pending';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    // Priority Constants
    const PRIORITY_LOW    = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH   = 'high';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
