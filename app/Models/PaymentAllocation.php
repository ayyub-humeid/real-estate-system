<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\{BelongsTo,MorphTo};
class PaymentAllocation extends Model { use \App\Traits\HasCompany; protected $fillable=['company_id','project_id','payment_id','allocatable_type','allocatable_id','payment_amount','actual_cost_amount','project_amount','exchange_rate_to_project']; protected $casts=['payment_amount'=>'decimal:4','actual_cost_amount'=>'decimal:4','project_amount'=>'decimal:4','exchange_rate_to_project'=>'decimal:8']; public function payment():BelongsTo{return $this->belongsTo(Payment::class);} public function allocatable():MorphTo{return $this->morphTo();} }
