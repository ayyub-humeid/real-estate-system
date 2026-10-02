<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquisitionProperty extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'property_acquisition_id', 'property_id', 'share_percentage', 'allocated_value', 'notes'];
    protected $casts = ['share_percentage' => 'decimal:2', 'allocated_value' => 'decimal:2'];
    public function acquisition(): BelongsTo
    {
        return $this->belongsTo(PropertyAcquisition::class, 'property_acquisition_id');
    }
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}