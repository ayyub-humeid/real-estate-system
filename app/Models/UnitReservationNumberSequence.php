<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitReservationNumberSequence extends Model
{
    protected $fillable = ['company_id', 'year', 'next_number'];
    protected $casts = ['year' => 'integer', 'next_number' => 'integer'];
}
