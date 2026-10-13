<?php
namespace App\Models;
use App\Enums\CrmStageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class CrmPipelineStage extends Model { use \App\Traits\HasCompany; protected $fillable=['company_id','pipeline_id','code','label','stage_type','position','is_active']; protected $casts=['stage_type'=>CrmStageType::class,'is_active'=>'boolean','position'=>'integer']; public function pipeline(): BelongsTo{return $this->belongsTo(CrmPipeline::class,'pipeline_id');} public function requirements(): HasMany{return $this->hasMany(CrmStageRequirement::class,'pipeline_stage_id')->orderBy('position');} public function opportunities(): HasMany{return $this->hasMany(Opportunity::class,'pipeline_stage_id');} public function getDisplayLabelAttribute(): string { $defaults=['new'=>['New','جديد'],'contacted'=>['Contacted','تم التواصل'],'qualified'=>['Qualified','مؤهل'],'viewing'=>['Viewing','معاينة'],'negotiation'=>['Negotiation','تفاوض'],'won'=>['Won','رابحة'],'lost'=>['Lost','خاسرة']]; return isset($defaults[$this->code]) && in_array($this->label,$defaults[$this->code],true) ? __('crm.defaults.stages.'.$this->code) : $this->label; } }
