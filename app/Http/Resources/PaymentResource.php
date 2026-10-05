<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Canonical project-cost cash movement representation. */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'party_id' => $this->party_id,
            'direction' => $this->direction,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'project_amount' => (float) $this->project_amount,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'reference_number' => $this->reference_number,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
        ];
    }
}
