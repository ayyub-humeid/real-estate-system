<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyOwnership extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'property_id', 'party_id', 'ownership_percentage', 'start_date', 'end_date', 'acquisition_id', 'notes'];
    protected $casts = ['ownership_percentage' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
    public function acquisition(): BelongsTo
    {
        return $this->belongsTo(PropertyAcquisition::class, 'acquisition_id');
    }
}