<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = ['planning', 'approved', 'in_progress', 'completed', 'closed', 'cancelled'];

    protected $fillable = ['company_id', 'name', 'project_type', 'status', 'start_date', 'expected_completion_date', 'description', 'approved_by', 'approved_at', 'completed_at', 'closed_at', 'cancelled_at', 'cancellation_reason'];
    protected $casts = ['start_date' => 'date', 'expected_completion_date' => 'date', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    public function projectProperties(): HasMany
    {
        return $this->hasMany(ProjectProperty::class);
    }
    public function activeProjectProperties(): HasMany
    {
        return $this->projectProperties()->whereNull('detached_at');
    }
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('ended_at');
    }
    public function buildings(): HasMany
    {
        return $this->hasMany(ProjectBuilding::class);
    }
}
