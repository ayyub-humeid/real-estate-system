<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'logo'                 => $this->logo ? asset('storage/' . $this->logo) : null,
            'verified'             => (bool) $this->verified,
            'relation'             => $this->relation,
            'badge'                => $this->badge,
            'badgeType'            => $this->badge_type,
            'hq'                   => $this->hq,
            'branches'             => $this->branches,
            'rating'               => (float) $this->rating,
            'years_active'         => (int) $this->years_active,
            'partner_developers'   => $this->partner_developers,
            'phone'                => $this->phone,
            'email'                => $this->email,
            'aboutTitle'           => $this->about_title,
            'aboutDescription'     => $this->about_description,
            'aboutSubDescription'  => $this->about_sub_description,

            // جلب صور أول 3 وكلاء كـ Array
            'agents_avatars' => $this->whenLoaded('agents', function () {
                return $this->agents
                    ->take(3)
                    ->map(fn ($agent) => $agent->avatar_url ?? null)
                    ->filter()
                    ->values()
                    ->toArray();
            }, []),

            'agents_count'     => $this->agents_count ?? $this->whenLoaded('agents', fn () => $this->agents->count(), 0),
            'properties_count' => $this->units_count  ?? $this->whenLoaded('units',  fn () => $this->units->count(),  0),

            // مخزون الوحدات العقارية للـ Portfolio
            'inventory' => UnitResource::collection($this->whenLoaded('units')),
        ];
    }
}
