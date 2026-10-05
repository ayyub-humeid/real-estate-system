<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Project extends Model
{
    use \App\Traits\HasCompany;

    public const STATUSES = ['planning', 'approved', 'in_progress', 'completed', 'closed', 'cancelled'];

    protected $fillable = ['company_id', 'name', 'project_type', 'currency', 'status', 'start_date', 'expected_completion_date', 'description', 'approved_by', 'approved_at', 'completed_at', 'closed_at', 'cancelled_at', 'cancellation_reason'];
    protected $casts = ['start_date' => 'date', 'expected_completion_date' => 'date', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $project): void {
            if (! $project->isDirty('currency')) return;

            $hasFinancialHistory = $project->budgets()->exists()
                || $project->commitments()->exists()
                || $project->actualCosts()->exists()
                || $project->payments()->exists();

            if ($hasFinancialHistory) {
                throw ValidationException::withMessages([
                    'currency' => 'Project currency is locked once financial history exists.',
                ]);
            }
        });
    }

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
    public function designPackages(): HasMany
    {
        return $this->hasMany(ProjectDesignPackage::class);
    }
    public function budgets(): HasMany { return $this->hasMany(ProjectBudget::class); }
    public function commitments(): HasMany { return $this->hasMany(FinancialCommitment::class); }
    public function actualCosts(): HasMany { return $this->hasMany(ActualCost::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
