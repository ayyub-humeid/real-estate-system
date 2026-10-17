<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationStatusHistory extends Model
{
    use \App\Traits\HasCompany;
    public const UPDATED_AT = null;
    protected $fillable = ['company_id', 'reservation_id', 'from_status', 'to_status', 'action', 'reason', 'metadata', 'changed_by', 'created_at'];
    protected $casts = ['from_status' => ReservationStatus::class, 'to_status' => ReservationStatus::class, 'metadata' => 'array', 'created_at' => 'datetime'];
    public function reservation(): BelongsTo { return $this->belongsTo(UnitReservation::class, 'reservation_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
