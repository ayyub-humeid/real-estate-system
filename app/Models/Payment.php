<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};

class Payment extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = ['pending', 'completed', 'failed', 'voided'];

    protected $fillable = [
        'company_id', 'project_id', 'party_id', 'direction', 'amount', 'currency',
        'project_amount', 'exchange_rate_to_project', 'payment_date', 'reference_number',
        'payment_method', 'status', 'completed_at', 'recorded_by', 'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:4', 'project_amount' => 'decimal:4',
        'exchange_rate_to_project' => 'decimal:8', 'payment_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function party(): BelongsTo { return $this->belongsTo(Party::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }
}
