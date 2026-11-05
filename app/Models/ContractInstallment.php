<?php

namespace App\Models;

use App\Enums\ContractInstallmentKind;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractInstallment extends Model
{
    use HasCompany;

    protected $fillable = [
        'company_id', 'contract_payment_schedule_id', 'replaces_installment_id', 'sequence',
        'kind', 'amount', 'currency', 'due_date', 'status', 'cancelled_by', 'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'kind' => ContractInstallmentKind::class,
        'amount' => 'decimal:4',
        'due_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function schedule(): BelongsTo { return $this->belongsTo(ContractPaymentSchedule::class, 'contract_payment_schedule_id'); }
    public function replaces(): BelongsTo { return $this->belongsTo(self::class, 'replaces_installment_id'); }
    public function replacementInstallments(): HasMany { return $this->hasMany(self::class, 'replaces_installment_id'); }
    public function allocations(): HasMany { return $this->hasMany(ContractPaymentAllocation::class); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
}
