<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProjectMember extends Model
{
    use \App\Traits\HasCompany;
    protected $fillable = ['company_id', 'project_id', 'user_id', 'role', 'started_at', 'ended_at', 'notes'];
    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
