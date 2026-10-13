<?php
namespace App\Models;
use App\Enums\CrmInquirySource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LeadInquiry extends Model { use \App\Traits\HasCompany; protected $fillable=['company_id','lead_id','listing_id','source','source_reference','contact_result','message','metadata','created_by']; protected $casts=['source'=>CrmInquirySource::class,'metadata'=>'array']; public function lead(): BelongsTo{return $this->belongsTo(Lead::class);} public function listing(): BelongsTo{return $this->belongsTo(Listing::class);} public function createdBy(): BelongsTo{return $this->belongsTo(User::class,'created_by');} }