<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractFinancialNotificationRoute extends Model
{
    use HasCompany;

    protected $fillable = ['company_id', 'event_type', 'user_id', 'is_fallback'];

    protected $casts = ['is_fallback' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
