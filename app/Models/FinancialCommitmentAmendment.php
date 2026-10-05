<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FinancialCommitmentAmendment extends Model { use \App\Traits\HasCompany; protected $attributes=['status'=>'draft']; protected $fillable=['company_id','financial_commitment_id','amount_change','budget_amount_change','reason','status','requested_by','requested_at','approved_by','approved_at']; protected $casts=['amount_change'=>'decimal:4','budget_amount_change'=>'decimal:4','requested_at'=>'datetime','approved_at'=>'datetime']; public function commitment():BelongsTo{return $this->belongsTo(FinancialCommitment::class,'financial_commitment_id');} }
