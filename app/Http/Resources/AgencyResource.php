<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgencyResource extends JsonResource
{
    /**
     * تحويل Company إلى شكل Agency للواجهة الأمامية.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo' => $this->logo ? asset('storage/' . $this->logo) : null,
            'verified' => (bool) $this->verified,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,

            // حقول إضافية للواجهة من جدول companies
            'relation' => $this->relation,
            'badge' => $this->badge,
            'badgeType' => $this->badge_type,
            'hq' => $this->hq ?? $this->address,
            'branches' => $this->branches,
            'rating' => (float) ($this->rating ?? 0.0),
            'years_active' => (int) ($this->years_active ?? 1),
            'partner_developers' => $this->partner_developers,
            'aboutTitle' => $this->about_title,
            'aboutDescription' => $this->about_description,
            'aboutSubDescription' => $this->about_sub_description,

            // الموظفون (بديل عن الوكلاء)
            'agents_avatars' => $this->whenLoaded('employees', function () {
                $placeholders = [
                    'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80',
                    'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=100&q=80',
                    'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=100&q=80'
                ];
                return $this->employees
                    ->take(3)
                    ->values()
                    ->map(function ($emp, $idx) use ($placeholders) {
                        if ($emp->avatar) {
                            return filter_var($emp->avatar, FILTER_VALIDATE_URL) ? $emp->avatar : asset('storage/' . $emp->avatar);
                        }
                        return $placeholders[$idx % count($placeholders)];
                    })
                    ->toArray();
            }, []),

            'agents_count' => $this->employees_count ?? $this->whenLoaded('employees', fn() => $this->employees->count(), 0),
            'properties_count' => $this->units_count ?? $this->whenLoaded('units', fn() => $this->units->count(), 0),

            // مخزون الوحدات للـ Portfolio (مُهيأ بالشكل الذي تتوقعه واجهة Next.js)
            'inventory' => $this->whenLoaded('units', function () {
                return $this->units->map(function ($unit) {
                    // تحديد مسار الصورة الأساسية للوحدة
                    $imagePath = null;
                    if ($unit->relationLoaded('primaryImage') && $unit->primaryImage) {
                        $imagePath = $unit->primaryImage->url;
                    } elseif ($unit->relationLoaded('images')) {
                        $primary = $unit->images->where('is_primary', true)->first() ?? $unit->images->first();
                        $imagePath = $primary ? $primary->url : null;
                    }

                    // إذا لم توجد صورة للوحدة، نستخدم صورة من الممتلكات (Property) أو صورة افتراضية
                    if (!$imagePath && $unit->property && $unit->property->relationLoaded('images')) {
                        $propPrimary = $unit->property->images->where('is_primary', true)->first() ?? $unit->property->images->first();
                        $imagePath = $propPrimary ? $propPrimary->url : null;
                    }

                    if ($imagePath && !filter_var($imagePath, FILTER_VALIDATE_URL)) {
                        $imagePath = asset('storage/' . $imagePath);
                    }

                    // صورة افتراضية رائعة في حال عدم توفر أي صورة
                    if (!$imagePath) {
                        $imagePath = 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=600&q=80';
                    }

                    return [
                        'id' => $unit->id,
                        'title' => ($unit->property ? $unit->property->name : 'Unit') . ' ' . $unit->unit_number,
                        'price' => '$' . number_format($unit->rent_price) . '/mo',
                        'address' => $unit->property ? $unit->property->address : ($this->address ?? 'N/A'),
                        'beds' => (int) ($unit->bedrooms ?? 0),
                        'baths' => (int) ($unit->bathrooms ?? 0),
                        'sqft' => $unit->sqft ?? '0',
                        'image' => $imagePath,
                        'status' => strtoupper('UNIT ' . ($unit->status ?? 'AVAILABLE')),
                    ];
                });
            }),
        ];
    }
}
