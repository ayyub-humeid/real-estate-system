<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitOwnership extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = ['company_id', 'unit_id', 'party_id', 'ownership_percentage', 'start_date', 'end_date', 'notes', 'changed_by'];
    protected $casts = ['ownership_percentage' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function party(): BelongsTo { return $this->belongsTo(Party::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
