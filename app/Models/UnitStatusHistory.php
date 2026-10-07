<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitStatusHistory extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = ['company_id', 'unit_id', 'from_status', 'to_status', 'reason', 'notes', 'changed_by', 'changed_at'];
    protected $casts = ['changed_at' => 'datetime'];

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
