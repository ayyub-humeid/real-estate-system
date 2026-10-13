<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CrmPipeline extends Model { use \App\Traits\HasCompany; protected $fillable=['company_id','name','is_default','is_active']; protected $casts=['is_default'=>'boolean','is_active'=>'boolean']; public function stages(): HasMany{return $this->hasMany(CrmPipelineStage::class,'pipeline_id')->orderBy('position');} public function opportunities(): HasMany{return $this->hasMany(Opportunity::class,'pipeline_id');} public function getDisplayNameAttribute(): string { return $this->is_default && in_array($this->name,['Sales Pipeline','مسار المبيعات'],true) ? __('crm.defaults.pipeline') : $this->name; } }
