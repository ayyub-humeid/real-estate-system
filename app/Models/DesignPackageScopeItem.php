<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DesignPackageScopeItem extends Model
{
    use \App\Traits\HasCompany;
    public const STATUSES = [
        'pending',
        'in_progress',
        'ready',
        'cancelled'
    ];
    protected $fillable = [
        'company_id',
        'design_package_assignment_id',
        'code',
        'title',
        'description',
        'sort_order',
        'target_date',
        'completed_at'
    ];
    protected $casts = [
        'target_date' => 'date',
        'completed_at' => 'datetime'
    ];
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DesignPackageAssignment::class, 'design_package_assignment_id');
    }
    public function activities(): HasMany
    {
        return $this->hasMany(DesignPackageActivity::class);
    }
    public function findings(): HasMany
    {
        return $this->hasMany(DesignReviewFinding::class);
    }
}
