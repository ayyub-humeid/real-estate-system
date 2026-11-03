<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Serializes human-readable acquisition references per company and year. */
class PropertyAcquisitionNumberSequence extends Model
{
    protected $fillable = ['company_id', 'year', 'next_number'];

    protected $casts = ['year' => 'integer', 'next_number' => 'integer'];
}
