<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DesignPackageReview extends Model
{
    use \App\Traits\HasCompany;
    public const STATUSES = ['pending', 'in_review', 'completed', 'cancelled'];
    protected $fillable = ['company_id', 'design_package_submission_id', 'reviewer_id', 'started_at', 'completed_at', 'summary'];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    public function submission(): BelongsTo
    {
        return $this->belongsTo(DesignPackageSubmission::class, 'design_package_submission_id');
    }
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
    public function findings(): HasMany
    {
        return $this->hasMany(DesignReviewFinding::class);
    }
}
