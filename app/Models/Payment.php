<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};

class Payment extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = ['pending', 'completed', 'failed', 'voided'];

    protected $fillable = [
        'company_id', 'project_id', 'contract_id', 'contract_payment_schedule_id', 'party_id', 'direction', 'payment_scope', 'idempotency_key', 'amount', 'currency',
        'project_amount', 'exchange_rate_to_project', 'payment_date', 'reference_number',
        'payment_method', 'status', 'completed_at', 'recorded_by', 'void_reason', 'reversal_of_payment_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4', 'project_amount' => 'decimal:4',
        'exchange_rate_to_project' => 'decimal:8', 'payment_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function contractPaymentSchedule(): BelongsTo { return $this->belongsTo(ContractPaymentSchedule::class); }
    public function party(): BelongsTo { return $this->belongsTo(Party::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }
    public function contractAllocations(): HasMany { return $this->hasMany(ContractPaymentAllocation::class); }
    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_payment_id'); }
    public function reversals(): HasMany { return $this->hasMany(self::class, 'reversal_of_payment_id'); }
}
