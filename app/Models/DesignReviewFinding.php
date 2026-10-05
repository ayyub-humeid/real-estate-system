<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DesignReviewFinding extends Model
{
    use \App\Traits\HasCompany;
    public const SEVERITIES = ['info', 'minor', 'major', 'critical'];
    public const STATUSES = ['open', 'addressed', 'accepted', 'waived'];
    protected $fillable = ['company_id', 'design_package_review_id', 'design_package_scope_item_id', 'document_version_id', 'severity', 'title', 'description', 'resolution_notes', 'resolved_by', 'resolved_at'];
    protected $casts = ['resolved_at' => 'datetime'];
    public function review(): BelongsTo
    {
        return $this->belongsTo(DesignPackageReview::class, 'design_package_review_id');
    }
    public function scopeItem(): BelongsTo
    {
        return $this->belongsTo(DesignPackageScopeItem::class, 'design_package_scope_item_id');
    }
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
    public function revisionLinks(): HasMany
    {
        return $this->hasMany(DesignRevisionFinding::class);
    }
}
