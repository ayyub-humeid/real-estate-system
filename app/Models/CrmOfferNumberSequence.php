<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmOfferNumberSequence extends Model
{
    protected $fillable = ['company_id', 'year', 'next_number'];
    protected $casts = ['year' => 'integer', 'next_number' => 'integer'];
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
