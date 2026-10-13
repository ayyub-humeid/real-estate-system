<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OpportunityStageHistory extends Model { use \App\Traits\HasCompany; public $timestamps=false; protected $fillable=['company_id','opportunity_id','from_stage_id','to_stage_id','changed_by','changed_at','metadata']; protected $casts=['changed_at'=>'datetime','metadata'=>'array']; public function opportunity(): BelongsTo{return $this->belongsTo(Opportunity::class);} public function fromStage(): BelongsTo{return $this->belongsTo(CrmPipelineStage::class,'from_stage_id');} public function toStage(): BelongsTo{return $this->belongsTo(CrmPipelineStage::class,'to_stage_id');} public function changedBy(): BelongsTo{return $this->belongsTo(User::class,'changed_by');} }