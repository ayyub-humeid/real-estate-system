<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractFinancialNotificationDelivery extends Model
{
    protected $fillable = ['contract_financial_event_id', 'user_id', 'channel', 'delivered_at'];

    protected $casts = ['delivered_at' => 'datetime'];

    public function event(): BelongsTo { return $this->belongsTo(ContractFinancialEvent::class, 'contract_financial_event_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
