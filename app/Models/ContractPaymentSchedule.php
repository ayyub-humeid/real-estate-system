<?php

namespace App\Models;

use App\Enums\ContractPaymentScheduleStatus;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractPaymentSchedule extends Model
{
    use HasCompany;

    protected $fillable = [
        'company_id', 'contract_id', 'contract_version_id', 'revision_number',
        'supersedes_schedule_id', 'status', 'currency', 'total_amount', 'assigned_to',
        'reason', 'created_by', 'submitted_by', 'submitted_at', 'issued_by', 'issued_at',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'status' => ContractPaymentScheduleStatus::class,
        'total_amount' => 'decimal:4',
        'submitted_at' => 'datetime',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function contractVersion(): BelongsTo { return $this->belongsTo(ContractVersion::class); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_schedule_id'); }
    public function revisions(): HasMany { return $this->hasMany(self::class, 'supersedes_schedule_id'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function issuedBy(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function installments(): HasMany { return $this->hasMany(ContractInstallment::class)->orderBy('sequence'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
