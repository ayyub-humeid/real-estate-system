<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractFinancialEvent extends Model
{
    use HasCompany;

    protected $fillable = [
        'company_id', 'contract_id', 'contract_payment_schedule_id', 'contract_installment_id',
        'payment_id', 'event_type', 'idempotency_key', 'payload', 'actor_id', 'occurred_at',
    ];

    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function schedule(): BelongsTo { return $this->belongsTo(ContractPaymentSchedule::class, 'contract_payment_schedule_id'); }
    public function installment(): BelongsTo { return $this->belongsTo(ContractInstallment::class, 'contract_installment_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function deliveries(): HasMany { return $this->hasMany(ContractFinancialNotificationDelivery::class); }
}
