<?php

namespace App\Models;

use App\Enums\DesignPackageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** One design deliverable of a project (e.g. "Architecture – Tower A") and its drawing versions. */
class ProjectDesignPackage extends Model
{
    use \App\Traits\HasCompany;

    public const DISCIPLINES = ['architectural', 'structural', 'electrical', 'mechanical', 'civil', 'interior', 'other'];

    protected $fillable = ['company_id', 'project_id', 'name', 'discipline', 'engineering_office_id', 'target_date', 'notes', 'created_by'];

    protected $casts = ['status' => DesignPackageStatus::class, 'target_date' => 'date'];

    public static function disciplineOptions(): array
    {
        return collect(self::DISCIPLINES)
            ->mapWithKeys(fn (string $discipline): array => [$discipline => __('project.design_disciplines.'.$discipline)])
            ->all();
    }

    public static function disciplineLabel(?string $discipline): string
    {
        return filled($discipline) ? __('project.design_disciplines.'.$discipline) : __('—');
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function engineeringOffice(): BelongsTo { return $this->belongsTo(Party::class, 'engineering_office_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function versions(): HasMany { return $this->hasMany(DesignPackageVersion::class)->orderByDesc('version_number'); }
    public function latestVersion(): HasOne { return $this->hasOne(DesignPackageVersion::class)->latestOfMany('version_number'); }
    public function documents(): MorphMany { return $this->morphMany(Document::class, 'documentable'); }
}
