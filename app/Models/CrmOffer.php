<?php

namespace App\Models;

use App\Enums\{OfferRevisionStatus, OfferStatus, OpportunityType};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, HasManyThrough};

class CrmOffer extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = ['company_id', 'opportunity_id', 'listing_id', 'unit_id', 'reference_number', 'transaction_type', 'assigned_to', 'created_by'];
    protected $casts = [
        'status' => OfferStatus::class,
        'transaction_type' => OpportunityType::class,
        'closed_at' => 'datetime',
        'next_revision_number' => 'integer'
    ];
    /**
     * A name people can recognise: who the offer is for and what it is about, e.g. "Nour · U-101 · Sale".
     * The reference number stays the exact identifier; this is what to show first.
     */
    public function displayName(bool $withType = true): string
    {
        $subject = $this->unit?->unit_number ?: $this->listing?->title;
        $type = $withType && $this->transaction_type instanceof OpportunityType ? $this->transaction_type->getLabel() : null;
        $name = collect([$this->opportunity?->lead?->name, $subject, $type])->filter()->implode(' · ');

        return $name !== '' ? $name : (string) $this->reference_number;
    }

    /** The name together with the reference, for places that must be exact, such as notifications. */
    public function displayNameWithReference(): string
    {
        $name = $this->displayName(false);

        return $name === (string) $this->reference_number ? $name : $name.' ('.$this->reference_number.')';
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function revisions(): HasMany
    {
        return $this->hasMany(CrmOfferRevision::class, 'offer_id')->orderByDesc('revision_number');
    }
    public function latestRevision(): HasOne
    {
        return $this->hasOne(CrmOfferRevision::class, 'offer_id')
            ->ofMany('revision_number', 'max');
    }
    public function agreedRevision(): HasOne
    {
        return $this->hasOne(CrmOfferRevision::class, 'offer_id')
            ->where('status', OfferRevisionStatus::Agreed->value);
    }
    public function approvals(): HasManyThrough
    {
        return $this->hasManyThrough(CrmOfferApproval::class, CrmOfferRevision::class, 'offer_id', 'offer_revision_id');
    }
    public function reservations(): HasMany
    {
        return $this->hasMany(UnitReservation::class, 'offer_id');
    }
}
