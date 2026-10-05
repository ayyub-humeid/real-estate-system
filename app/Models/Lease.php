<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Lease terms only; Phase 08 introduces customer schedules/installments. */
class Lease extends Model
{
    use SoftDeletes, \App\Traits\HasCompany;

    protected static function booted(): void
    {
        static::creating(function (self $lease): void {
            if ($lease->company_id) return;
            $property = $lease->property_id ? Property::find($lease->property_id) : $lease->unit?->property;
            if ($property) $lease->company_id = $property->company_id;
        });
    }

    protected $fillable = ['company_id','property_id','unit_id','tenant_id','start_date','end_date','rent_amount','deposit_amount','payment_frequency','payment_day','status','termination_date','termination_reason','notes','special_terms'];
    protected $casts = ['start_date'=>'date','end_date'=>'date','termination_date'=>'date','rent_amount'=>'decimal:2','deposit_amount'=>'decimal:2','payment_day'=>'integer'];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function documents(): MorphMany { return $this->morphMany(Document::class, 'documentable'); }

    public function scopeActive($query) { return $query->where('status', 'active'); }
    public function scopeDraft($query) { return $query->where('status', 'draft'); }
    public function scopeExpiringSoon($query, $days = 30) { return $query->where('status','active')->whereNotNull('end_date')->whereBetween('end_date',[now(),now()->addDays($days)]); }
    public function scopeExpired($query) { return $query->where('status','active')->whereNotNull('end_date')->where('end_date','<',now()); }
    public function scopeTerminated($query) { return $query->where('status','terminated'); }

    public function getIsActiveAttribute(): bool { return $this->status === 'active'; }
    public function getIsExpiredAttribute(): bool { return (bool) ($this->end_date?->isPast()); }
    public function getDaysRemainingAttribute(): ?int { return !$this->end_date || $this->is_expired ? null : now()->diffInDays($this->end_date, false); }
    public function getDurationInMonthsAttribute(): ?int { return $this->end_date ? $this->start_date->diffInMonths($this->end_date) : null; }

    public function terminate(string $reason, ?Carbon $date = null): bool
    {
        $this->update(['status'=>'terminated','termination_date'=>$date ?? now(),'termination_reason'=>$reason]);
        $this->unit?->update(['status'=>'available']);
        return true;
    }

    public function renew(Carbon $newEndDate, ?float $newRentAmount = null): self
    {
        $newLease = $this->replicate();
        $newLease->start_date = $this->end_date->copy()->addDay();
        $newLease->end_date = $newEndDate;
        $newLease->rent_amount = $newRentAmount ?? $this->rent_amount;
        $newLease->status = 'active';
        $newLease->save();
        $this->update(['status'=>'renewed']);
        return $newLease;
    }
}
