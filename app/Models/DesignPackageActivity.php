<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DesignPackageActivity extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'project_design_package_id', 'design_package_scope_item_id', 'type', 'description', 'occurred_at', 'performed_by', 'metadata'];
    protected $casts = ['occurred_at' => 'datetime', 'metadata' => 'array'];
    public function package(): BelongsTo
    {
        return $this->belongsTo(ProjectDesignPackage::class, 'project_design_package_id');
    }
    public function scopeItem(): BelongsTo
    {
        return $this->belongsTo(DesignPackageScopeItem::class, 'design_package_scope_item_id');
    }
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
