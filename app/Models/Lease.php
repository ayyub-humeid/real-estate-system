<?php
// app/Models/Lease.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Lease extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'unit_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit_amount',
        'payment_frequency',
        'payment_day',
        'status',
        'termination_date',
        'termination_reason',
        'notes',
        'special_terms',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'termination_date' => 'date',
        'rent_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'payment_day' => 'integer',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now());
    }

    // Accessors & Helpers
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date || $this->is_expired) {
            return null;
        }
        return now()->diffInDays($this->end_date, false);
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->where('status', 'paid')->sum('paid_amount');
    }

    public function getTotalOutstandingAttribute(): float
    {
        return $this->payments()
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->sum('remaining_amount');
    }

    public function getDurationInMonthsAttribute(): ?int
    {
        if (!$this->end_date) {
            return null; // Open-ended lease
        }
        return $this->start_date->diffInMonths($this->end_date);
    }

    // Business Logic Methods
    public function terminate(string $reason, ?Carbon $date = null): bool
    {
        $this->update([
            'status' => 'terminated',
            'termination_date' => $date ?? now(),
            'termination_reason' => $reason,
        ]);

        // Update unit status back to available
        $this->unit->update(['status' => 'available']);

        return true;
    }

    public function renew(Carbon $newEndDate, ?float $newRentAmount = null): self
    {
        // Create new lease based on current one
        $newLease = $this->replicate();
        $newLease->start_date = $this->end_date->addDay();
        $newLease->end_date = $newEndDate;
        $newLease->rent_amount = $newRentAmount ?? $this->rent_amount;
        $newLease->status = 'draft';
        $newLease->save();

        // Mark current lease as renewed
        $this->update(['status' => 'renewed']);

        return $newLease;
    }

    public function generatePaymentSchedule(): void
    {
        if ($this->status !== 'active') {
            return;
        }

        $startDate = $this->start_date->copy();
        $endDate = $this->end_date ?? $startDate->copy()->addYear(); // Default 1 year if open-ended

        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Set to payment day of month
            $dueDate = $currentDate->copy()->day($this->payment_day);

            // Create payment if doesn't exist
            $this->payments()->firstOrCreate(
                ['due_date' => $dueDate],
                [
                    'amount' => $this->rent_amount,
                    'remaining_amount' => $this->rent_amount,
                    'status' => 'pending',
                ]
            );

            // Move to next period
            $currentDate = match($this->payment_frequency) {
                'monthly' => $currentDate->addMonth(),
                'quarterly' => $currentDate->addMonths(3),
                'semi_annually' => $currentDate->addMonths(6),
                'yearly' => $currentDate->addYear(),
            };
        }
    }
}