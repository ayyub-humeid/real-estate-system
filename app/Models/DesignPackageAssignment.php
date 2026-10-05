<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DesignPackageAssignment extends Model
{
    use \App\Traits\HasCompany;
    public const STATUSES = [
        'active',
        'completed',
        'cancelled',
        'replaced'
    ];
    protected $fillable = [
        'company_id',
        'project_design_package_id',
        'party_id',
        'assigned_at',
        'ended_at',
        'notes',
        'assigned_by'
    ];
    protected $casts = [
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime'
    ];
    public function package(): BelongsTo
    {
        return $this->belongsTo(ProjectDesignPackage::class, 'project_design_package_id');
    }
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
    public function scopeItems(): HasMany
    {
        return $this->hasMany(DesignPackageScopeItem::class);
    }
    public function submissions(): HasMany
    {
        return $this->hasMany(DesignPackageSubmission::class);
    }
}
