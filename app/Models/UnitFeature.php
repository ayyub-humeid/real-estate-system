<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitFeature extends Model
{
    protected $fillable = ['unit_id', 'feature_key', 'name', 'value'];

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
