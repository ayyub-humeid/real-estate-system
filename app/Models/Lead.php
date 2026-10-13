<?php
namespace App\Models;
use App\Enums\{CrmInquirySource, LeadStatus};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Lead extends Model { use \App\Traits\HasCompany; protected $fillable=['company_id','name','phone','normalized_phone','email','normalized_email','status','first_source','assigned_to','created_by','updated_by']; protected $casts=['status'=>LeadStatus::class,'first_source'=>CrmInquirySource::class]; public function assignedTo(): BelongsTo{return $this->belongsTo(User::class,'assigned_to');} public function inquiries(): HasMany{return $this->hasMany(LeadInquiry::class)->latest();} public function opportunities(): HasMany{return $this->hasMany(Opportunity::class);} public function createdBy(): BelongsTo{return $this->belongsTo(User::class,'created_by');} }