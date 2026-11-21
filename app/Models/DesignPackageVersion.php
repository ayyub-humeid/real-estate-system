<?php

namespace App\Models;

use App\Enums\DesignVersionDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One numbered drawing upload of a design package and the decision taken on it. Immutable once decided. */
class DesignPackageVersion extends Model
{
    use \App\Traits\HasCompany;

    protected $fillable = ['company_id', 'project_design_package_id', 'version_number', 'document_version_id', 'notes', 'uploaded_by'];

    protected $casts = ['decision' => DesignVersionDecision::class, 'version_number' => 'integer', 'decided_at' => 'datetime'];

    public function package(): BelongsTo { return $this->belongsTo(ProjectDesignPackage::class, 'project_design_package_id'); }
    public function documentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function decidedBy(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
