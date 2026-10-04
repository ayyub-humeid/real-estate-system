<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProjectProperty extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'project_id', 'property_id', 'role', 'notes', 'attached_at', 'detached_at'];
    protected $casts = ['attached_at' => 'datetime', 'detached_at' => 'datetime'];
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
