<?php

namespace App\Models;

use App\Enums\CrmAssignmentMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = [
        'company_id',
        'logo',
        'contract_background',
        'contract_signature',
        'contract_stamp',
        'company_legal_name',
        'company_address',
        'company_phone',
        'company_email',
        'tax_id',
        'registration_number',
        'website',
        'contract_terms',
        'contract_footer_text',
        'contract_header_color',
        'show_contract_stamp',
        'receipt_terms',
        'receipt_header_color',
        'payment_grace_period_days',
        'late_payment_fee_percentage',
        'allow_maintenance_unit_marketing',
        'crm_assignment_mode',
        'crm_round_robin_last_user_id',
    ];

    protected $casts = [
        'crm_assignment_mode' => CrmAssignmentMode::class,
        'show_contract_stamp' => 'boolean',
        'payment_grace_period_days' => 'integer',
        'late_payment_fee_percentage' => 'decimal:2',
        'allow_maintenance_unit_marketing' => 'boolean',
    ];

    // ✅ Helper: Get logo URL
    public function getLogoUrlAttribute(): ?string
    {
        return storage_url($this->logo);
    }
    
    public function getContractBackgroundUrlAttribute(): ?string
    {
        return storage_url($this->contract_background);
    }

    public function getContractSignatureUrlAttribute(): ?string
    {
        return storage_url($this->contract_signature);
    }

    public function getContractStampUrlAttribute(): ?string
    {
        return storage_url($this->contract_stamp);
    }
}
