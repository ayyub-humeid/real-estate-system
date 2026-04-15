<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes, \App\Traits\HasCompany;
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($payment) {
            // Automatically set company_id from the lease if not set
            if (!$payment->company_id && $payment->lease_id) {
                $lease = Lease::find($payment->lease_id);
                if ($lease) {
                    $payment->company_id = $lease->company_id;
                }
            }
        });

        static::saving(function ($payment) {
            $payment->remaining_amount = max(0, (float) ($payment->amount ?? 0) - (float) ($payment->paid_amount ?? 0));
        });
    }

    protected $fillable = [
        'company_id',
        'lease_id',
        'amount',
        'due_date',
        'payment_date',
        'payment_method',
        'reference_number',
        'check_number',
        'status',
        'paid_amount',
        'remaining_amount',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    /**
     * Computed remaining amount based on current paid vs installment amount.
     */
    public function getRemainingAmountAttribute($value): float
    {
        // If the column exists and is populated, use it. Otherwise calculate.
        if ($value !== null) {
            return (float) $value;
        }
        
        return max(0, (float) ($this->amount ?? 0) - (float) ($this->paid_amount ?? 0));
    }

    // Relationships
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
     public function tenant(): HasOneThrough
   {
       return $this->hasOneThrough(
           Tenant::class,
           Lease::class,
           'id',          // Foreign key on leases
           'id',          // Foreign key on tenants
           'lease_id',    // Local key on payments
           'tenant_id'    // Local key on leases
       );
   }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->orWhere(function($q) {
                $q->whereIn('status', ['pending', 'partial'])
                  ->where('due_date', '<', now());
            });
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['pending', 'overdue', 'partial']);
    }

    // Accessors
    // app/Models/Payment.php

public function getPaymentSummaryAttribute(): string
{
    $lease = $this->lease;
    
    if (!$lease) {
        return 'select lease first!!';
    }

    // Use outstanding_balance from lease (total contract debt)
    $totalContractDebt = (float) ($lease->rent_amount * ($lease->payments_count ?: 1)); // Temporary fallback
    if ($lease->outstanding_balance) {
        $totalContractDebt = (float) $lease->outstanding_balance + (float) $lease->total_paid;
    }
    
    $totalPaidSoFar = (float) ($lease->total_paid ?? $lease->payments()->where('status', 'paid')->sum('paid_amount'));
    $remainingBalance = max(0, $totalContractDebt - $totalPaidSoFar);

    return number_format($totalContractDebt, 2) . " [Total] - " . 
           number_format($totalPaidSoFar, 2) . " [Paid] = " . 
           number_format($remainingBalance, 2) . " [Balance]";
}
    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'overdue' || 
               ($this->status === 'pending' && $this->due_date->isPast());
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (!$this->is_overdue) {
            return null;
        }
        return now()->diffInDays($this->due_date, false);
    }

    // Business Logic
    public function recordPayment(float $amount, string $method, ?string $reference = null): bool
    {
        $this->paid_amount = (float) ($this->paid_amount ?? 0) + $amount;
        $this->payment_method = $method;
        $this->reference_number = $reference;
        $this->payment_date = now();

        // Check against this payment's expected amount
        $expectedAmount = (float) ($this->amount ?? 0);
        
        if ($this->paid_amount >= $expectedAmount && $expectedAmount > 0) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        }

        // Always update remaining_amount column for database-level queries
        $this->remaining_amount = max(0, $expectedAmount - $this->paid_amount);

        return $this->save();
    }

    public function markAsOverdue(): bool
    {
        if ($this->status === 'pending' && $this->due_date->isPast()) {
            return $this->update(['status' => 'overdue']);
        }
        return false;
    }
}