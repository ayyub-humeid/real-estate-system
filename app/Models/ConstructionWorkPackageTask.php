<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionWorkPackageTask extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = ['planned', 'in_progress', 'awaiting_inspection', 'completed', 'cancelled'];

    protected $attributes = ['status' => 'planned', 'progress_percentage' => 0, 'requires_inspection' => false];

    protected $fillable = ['company_id', 'construction_work_package_id', 'assigned_party_id', 'name', 'description', 'planned_start_date', 'planned_end_date', 'requires_inspection', 'notes'];
    protected $casts = ['planned_start_date' => 'date', 'planned_end_date' => 'date', 'actual_start_date' => 'date', 'actual_end_date' => 'date', 'requires_inspection' => 'boolean', 'cancelled_at' => 'datetime', 'progress_percentage' => 'decimal:2'];

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(ConstructionWorkPackage::class, 'construction_work_package_id');
    }
    public function assignedParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'assigned_party_id');
    }
    public function progressUpdates(): HasMany
    {
        return $this->hasMany(ConstructionProgressUpdate::class);
    }
    public function inspections(): HasMany
    {
        return $this->hasMany(ConstructionInspection::class);
    }
    public function issues(): HasMany
    {
        return $this->hasMany(ConstructionIssue::class);
    }
    public function delays(): HasMany
    {
        return $this->hasMany(ConstructionDelay::class);
    }
}
