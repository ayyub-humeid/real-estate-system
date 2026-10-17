<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationSnapshot extends Model
{
    use \App\Traits\HasCompany;
    public const UPDATED_AT = null;
    protected $fillable = ['company_id', 'reservation_id', 'unit_data', 'commercial_terms', 'source_data', 'created_by', 'created_at'];
    protected $casts = ['unit_data' => 'array', 'commercial_terms' => 'array', 'source_data' => 'array', 'created_at' => 'datetime'];
    public function reservation(): BelongsTo { return $this->belongsTo(UnitReservation::class, 'reservation_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
