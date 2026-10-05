<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DesignRevisionFinding extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'design_package_revision_id', 'design_review_finding_id', 'notes'];
    public function revision(): BelongsTo
    {
        return $this->belongsTo(DesignPackageRevision::class, 'design_package_revision_id');
    }
    public function finding(): BelongsTo
    {
        return $this->belongsTo(DesignReviewFinding::class, 'design_review_finding_id');
    }
}
