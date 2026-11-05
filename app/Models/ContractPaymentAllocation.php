<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractPaymentAllocation extends Model
{
    use HasCompany;

    protected $fillable = ['company_id', 'payment_id', 'contract_installment_id', 'allocated_amount', 'currency', 'allocated_by'];

    protected $casts = ['allocated_amount' => 'decimal:4'];

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function installment(): BelongsTo { return $this->belongsTo(ContractInstallment::class, 'contract_installment_id'); }
    public function allocatedBy(): BelongsTo { return $this->belongsTo(User::class, 'allocated_by'); }
}
