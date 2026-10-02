<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquisitionParty extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'property_acquisition_id', 'party_id', 'role', 'share_percentage', 'notes'];
    protected $casts = ['share_percentage' => 'decimal:2'];
    public function acquisition(): BelongsTo
    {
        return $this->belongsTo(PropertyAcquisition::class, 'property_acquisition_id');
    }
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}