<?php
namespace App\Models;
use App\Enums\ContractApprovalStatus;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractApproval extends Model { use HasCompany; protected $fillable=['company_id','contract_version_id','reviewed_by','status','notes','reviewed_at']; protected $casts=['status' => ContractApprovalStatus::class, 'reviewed_at'=>'datetime']; public function version():BelongsTo{return $this->belongsTo(ContractVersion::class,'contract_version_id');} public function reviewer():BelongsTo{return $this->belongsTo(User::class,'reviewed_by');} }
