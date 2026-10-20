<?php
namespace App\Models;
use App\Enums\ContractAmendmentStatus;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractAmendment extends Model { use HasCompany; protected $fillable=['company_id','contract_id','base_contract_version_id','resulting_contract_version_id','amendment_type','status','reason','effective_date','created_by']; protected $casts=['status' => ContractAmendmentStatus::class, 'effective_date'=>'date']; public function contract():BelongsTo{return $this->belongsTo(Contract::class);} public function baseVersion():BelongsTo{return $this->belongsTo(ContractVersion::class,'base_contract_version_id');} public function resultingVersion():BelongsTo{return $this->belongsTo(ContractVersion::class,'resulting_contract_version_id');} }
