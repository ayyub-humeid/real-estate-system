<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OpportunityRequirementCompletion extends Model { use \App\Traits\HasCompany; protected $fillable=['company_id','opportunity_id','stage_requirement_id','completed_by','completed_at','notes']; protected $casts=['completed_at'=>'datetime']; public function opportunity(): BelongsTo{return $this->belongsTo(Opportunity::class);} public function requirement(): BelongsTo{return $this->belongsTo(CrmStageRequirement::class,'stage_requirement_id');} public function completedBy(): BelongsTo{return $this->belongsTo(User::class,'completed_by');} }