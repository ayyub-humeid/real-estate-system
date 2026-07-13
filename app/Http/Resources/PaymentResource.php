<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'payment_date' => $this->payment_date ? $this->payment_date->format('Y-m-d') : null,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'check_number' => $this->check_number,
            'status' => $this->status,
            'notes' => $this->notes,
            'lease' => [
                'id' => $this->whenLoaded('lease', fn() => $this->lease->id),
                'start_date' => $this->whenLoaded('lease', fn() => $this->lease->start_date?->format('Y-m-d')),
                'end_date' => $this->whenLoaded('lease', fn() => $this->lease->end_date?->format('Y-m-d')),
                'status' => $this->whenLoaded('lease', fn() => $this->lease->status),
                'unit' => $this->whenLoaded('lease', function () {
                    return $this->lease->relationLoaded('unit') && $this->lease->unit ? [
                        'id' => $this->lease->unit->id,
                        'unit_number' => $this->lease->unit->unit_number,
                        'property_id' => $this->lease->unit->property_id,
                        'property_name' => $this->lease->unit->property->name
                    ] : null;
                }),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
