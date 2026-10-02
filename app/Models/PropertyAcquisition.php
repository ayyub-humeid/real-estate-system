<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany,MorphMany};
class PropertyAcquisition extends Model { use \App\Traits\HasCompany; public const TYPES=['cash_purchase','installment_purchase','partnership','land_for_units','profit_sharing','owned_existing']; public const STATUSES=['draft','under_due_diligence','approved','completed','cancelled']; protected $fillable=['company_id','reference_number','type','status','acquisition_date','agreed_value','currency','description','notes','approved_by','approved_at','completed_at','cancelled_at','cancellation_reason']; protected $casts=['acquisition_date'=>'date','agreed_value'=>'decimal:2','approved_at'=>'datetime','completed_at'=>'datetime','cancelled_at'=>'datetime'];
 public function acquisitionProperties():HasMany{return $this->hasMany(AcquisitionProperty::class);}
 public function acquisitionParties():HasMany{return $this->hasMany(AcquisitionParty::class);}
  public function dueDiligenceCases():HasMany{return $this->hasMany(DueDiligenceCase::class);}
   public function ownerships():HasMany{return $this->hasMany(PropertyOwnership::class,'acquisition_id');}
    public function approver():BelongsTo{return $this->belongsTo(User::class,'approved_by');}
     public function documents():MorphMany{return $this->morphMany(Document::class,'documentable');}
      }
