<?php

namespace App\Models;

use App\Enums\RentFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPriceHistory extends Model
{
    protected $fillable = [
        'listing_id',
        'old_price',
        'new_price',
        'old_currency',
        'new_currency',
        'old_rent_frequency',
        'new_rent_frequency',
        'changed_by',
        'reason',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'old_rent_frequency' => RentFrequency::class,
        'new_rent_frequency' => RentFrequency::class,
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}