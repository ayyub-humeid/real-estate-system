<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    use \App\Traits\HasCompany;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->company_id) && $model->unit_id) {
                $unit = Unit::with('property')->find($model->unit_id);
                if ($unit && $unit->property) {
                    $model->company_id = $unit->property->company_id;
                }
            }
        });

        // The completion date follows the status, so it cannot be forgotten.
        static::saving(function (self $model): void {
            if ($model->status === MaintenanceStatus::Resolved->value) {
                $model->completed_at ??= now();
            } elseif ($model->exists && $model->isDirty('status')
                && $model->getOriginal('status') === MaintenanceStatus::Resolved->value && $model->isOpen()) {
                $model->completed_at = null;
            }
        });
    }

    protected $fillable = [
        'unit_id',
        'company_id',
        'reported_by_id',
        'title',
        'description',
        'status',
        'priority',
        'assigned_to_id',
        'internal_notes',
        'estimated_cost',
        'actual_cost',
        'scheduled_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    /** A record saved without a status gets the database default, which is "new". */
    public function isOpen(): bool
    {
        return MaintenanceStatus::tryFrom((string) ($this->status ?? MaintenanceStatus::New->value))?->isOpen() ?? false;
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reporter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    public function technician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function images(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->orderBy('order');
    }

    // --- Scopes ---

    public function scopeOpen($query)
    {
        return $query->whereIn('status', MaintenanceStatus::openValues());
    }

    public function scopeResolved($query)
    {
        return $query->where('status', MaintenanceStatus::Resolved->value);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [MaintenancePriority::High->value, MaintenancePriority::Emergency->value]);
    }

    public function scopeByTechnician($query, $userId)
    {
        return $query->where('assigned_to_id', $userId);
    }
}
