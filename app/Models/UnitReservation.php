<?php

namespace App\Models;

use App\Enums\{OpportunityType, ReservationSource, ReservationStatus, RentFrequency};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class UnitReservation extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = ['company_id', 'reservation_number', 'source', 'transaction_type', 'unit_id', 'customer_party_id', 'opportunity_id', 'offer_id', 'offer_revision_id', 'agreed_amount', 'currency', 'down_payment_amount', 'payment_method', 'payment_terms', 'rent_frequency', 'lease_term_value', 'lease_term_unit', 'deposit_amount', 'terms_json', 'valid_until'];
    protected $casts = ['source' => ReservationSource::class, 'status' => ReservationStatus::class, 'transaction_type' => OpportunityType::class, 'rent_frequency' => RentFrequency::class, 'agreed_amount' => 'decimal:2', 'down_payment_amount' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'terms_json' => 'array', 'lease_term_value' => 'integer', 'valid_until' => 'datetime', 'submitted_at' => 'datetime', 'activated_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime', 'expired_at' => 'datetime'];
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Party::class, 'customer_party_id'); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function offer(): BelongsTo { return $this->belongsTo(CrmOffer::class, 'offer_id'); }
    public function offerRevision(): BelongsTo { return $this->belongsTo(CrmOfferRevision::class, 'offer_revision_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function rejectedBy(): BelongsTo { return $this->belongsTo(User::class, 'rejected_by'); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function snapshot(): HasOne { return $this->hasOne(ReservationSnapshot::class, 'reservation_id'); }
    public function statusHistories(): HasMany { return $this->hasMany(ReservationStatusHistory::class, 'reservation_id')->orderByDesc('created_at')->orderByDesc('id'); }
}
