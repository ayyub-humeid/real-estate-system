<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class BudgetLine extends Model { use \App\Traits\HasCompany; protected $fillable=['company_id','project_id','created_from_budget_item_id']; public function project():BelongsTo{return $this->belongsTo(Project::class);} public function items():HasMany{return $this->hasMany(BudgetItem::class);} public function commitments():HasMany{return $this->hasMany(FinancialCommitment::class);} public function actualCosts():HasMany{return $this->hasMany(ActualCost::class);} }
