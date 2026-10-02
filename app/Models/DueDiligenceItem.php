<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DueDiligenceItem extends Model { use \App\Traits\HasCompany; public const STATUSES=['pending','passed','failed','waived']; protected $fillable=['company_id','due_diligence_case_id','title','category','description','is_required','status','checked_by','checked_at','notes','sort_order']; protected $casts=['is_required'=>'boolean','checked_at'=>'datetime']; public function dueDiligenceCase():BelongsTo{return $this->belongsTo(DueDiligenceCase::class);} public function checkedBy():BelongsTo{return $this->belongsTo(User::class,'checked_by');} }
