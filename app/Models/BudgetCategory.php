<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class BudgetCategory extends Model { use \App\Traits\HasCompany; protected $fillable=['company_id','project_budget_id','parent_id','cloned_from_id','name','code','description','sort_order']; public function budget():BelongsTo{return $this->belongsTo(ProjectBudget::class,'project_budget_id');} public function parent():BelongsTo{return $this->belongsTo(self::class,'parent_id');} public function children():HasMany{return $this->hasMany(self::class,'parent_id');} public function items():HasMany{return $this->hasMany(BudgetItem::class);} }
