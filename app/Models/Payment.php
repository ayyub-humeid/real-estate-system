<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
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

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->orWhere(function($q) {
                $q->where('status', 'pending')
                  ->where('due_date', '<', now());
            });
    }

    // Accessors
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
        $this->paid_amount += $amount;
        $this->remaining_amount = $this->amount - $this->paid_amount;
        $this->payment_method = $method;
        $this->reference_number = $reference;
        $this->payment_date = now();
        
        // Update status
        if ($this->paid_amount >= $this->amount) {
            $this->status = 'paid';
            $this->remaining_amount = 0;
        } else {
            $this->status = 'partial';
        }

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