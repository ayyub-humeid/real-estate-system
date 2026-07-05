<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    /**
     * عرض قائمة الوكالات مع دعم الفلترة والبحث.
     */
    public function index(Request $request)
    {
        $query = Agency::query();

        // 1. فلترة البحث بالاسم أو المنطقة أو المطور الشريك
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('hq', 'like', "%{$search}%")
                  ->orWhere('branches', 'like', "%{$search}%")
                  ->orWhere('partner_developers', 'like', "%{$search}%");
            });
        }

        // 2. فلترة المنطقة الجغرافية (Service Area)
        if ($request->filled('serviceArea') && $request->input('serviceArea') !== 'Service Area') {
            $area = $request->input('serviceArea');
            $query->where(function ($q) use ($area) {
                $q->where('hq', 'like', "%{$area}%")
                  ->orWhere('branches', 'like', "%{$area}%");
            });
        }

        // 3. فلترة حجم الشركة (Boutique / Enterprise) بناءً على عدد الوحدات
        if ($request->filled('size') && $request->input('size') !== 'Agency Size') {
            $size = $request->input('size');
            $query->withCount('units');
            if ($size === 'Boutique') {
                $query->having('units_count', '<', 200);
            } elseif ($size === 'Enterprise') {
                $query->having('units_count', '>=', 200);
            }
        }

        // 4. فلترة نوع الشراكة (Exclusive / Independent / Elite)
        if ($request->filled('type') && $request->input('type') !== 'Agency Type') {
            $type = $request->input('type');
            match ($type) {
                'Exclusive'               => $query->where('badge_type', 'exclusive'),
                'Independent'             => $query->where('relation', 'like', '%Independent%'),
                'Elite Developer Alliance' => $query->where('badge_type', 'elite'),
                default                   => null,
            };
        }

        // جلب النتائج مع حساب عدد الوحدات والوكلاء
        $agencies = $query
            ->withCount(['agents', 'units'])
            ->with(['agents' => fn ($q) => $q->select('id', 'agency_id', 'name')->limit(3)])
            ->paginate((int) $request->input('per_page', 10));

        return AgencyResource::collection($agencies);
    }

    /**
     * عرض تفاصيل وكالة واحدة مع علاقاتها.
     */
    public function show(Agency $agency)
    {
        $agency->loadCount(['agents', 'units']);
        $agency->load([
            'agents' => fn ($q) => $q->select('id', 'agency_id', 'name', 'email', 'phone'),
            'units'  => fn ($q) => $q->with(['property:id,name,address', 'primaryImage'])->limit(20),
        ]);

        return new AgencyResource($agency);
    }
}
