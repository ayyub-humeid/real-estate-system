<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->whenHas('id'),
            'name' => $this->whenHas('name'),
            'email' => $this->whenHas('email'),
            'phone' => $this->whenHas('phone'),
            'address' => $this->whenHas('address'),
            'logo_url' => $this->whenHas('logo', fn() => $this->logo ? asset('storage/' . $this->logo) : null),
            'is_active' => $this->whenHas('is_active'),
        ];
    }
}
