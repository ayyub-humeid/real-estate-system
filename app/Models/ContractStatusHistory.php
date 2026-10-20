<?php
namespace App\Models;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractStatusHistory extends Model { use HasCompany; protected $fillable=['company_id','contract_id','contract_version_id','from_status','to_status','action','reason','metadata','actor_id','changed_at']; protected $casts=['metadata'=>'array','changed_at'=>'datetime']; public function contract():BelongsTo{return $this->belongsTo(Contract::class);} public function version():BelongsTo{return $this->belongsTo(ContractVersion::class,'contract_version_id');} public function actor():BelongsTo{return $this->belongsTo(User::class,'actor_id');} }
