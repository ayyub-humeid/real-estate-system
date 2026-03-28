<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = [
        'company_id',
        'logo',
        'lease_background',
        'signature',
        'company_legal_name',
        'company_address',
        'company_phone',
        'company_email',
        'tax_id',
        'registration_number',
        'website',
        'lease_terms',
        'lease_footer_text',
        'lease_header_color',
        'show_company_stamp',
        'receipt_terms',
        'receipt_header_color',
        'payment_grace_period_days',
        'late_payment_fee_percentage',
    ];

    protected $casts = [
        'show_company_stamp' => 'boolean',
        'payment_grace_period_days' => 'integer',
        'late_payment_fee_percentage' => 'decimal:2',
    ];

    // ✅ Helper: Get logo URL
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
    
    // ✅ Helper: Get lease background URL
    public function getLeaseBackgroundUrlAttribute(): ?string
    {
        return $this->lease_background ? asset('storage/' . $this->lease_background) : null;
    }
    
    // ✅ Helper: Get signature URL
    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature ? asset('storage/' . $this->signature) : null;
    }
}