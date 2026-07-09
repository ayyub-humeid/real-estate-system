<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgencyResource;
use App\Models\Company;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    /**
     * عرض قائمة الشركات (agencies) مع دعم الفلترة والبحث.
     * نستخدم withoutGlobalScopes() لضمان عدم تطبيق أي scope يقيّد النتائج
     * عندما لا يكون هناك مستخدم مسجّل دخول (public API).
     */
    public function index(Request $request)
    {
        $query = Company::withoutGlobalScopes()->where('is_active', true);

        // فلترة بالبحث: الاسم، البريد، الهاتف، العنوان، الشركاء
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('partner_developers', 'like', "%{$search}%");
            });
        }

        // فلترة حجم الشركة بناءً على عدد الوحدات
        if ($request->filled('size') && $request->input('size') !== 'Agency Size') {
            $size = $request->input('size');
            $query->withCount('units');
            if ($size === 'Boutique') {
                $query->having('units_count', '<', 200);
            } elseif ($size === 'Enterprise') {
                $query->having('units_count', '>=', 200);
            }
        }

        // فلترة منطقة الخدمة (serviceArea)
        if ($request->filled('serviceArea') && $request->input('serviceArea') !== 'Service Area') {
            $area = $request->input('serviceArea');
            $query->where(function ($q) use ($area) {
                $q->where('hq', 'like', "%{$area}%")
                  ->orWhere('branches', 'like', "%{$area}%");
            });
        }

        // فلترة نوع الشراكة (type)
        if ($request->filled('type')) {
            $type = $request->input('type');
            if (strtolower($type) === 'exclusive') {
                $query->where('badge_type', 'exclusive');
            } elseif (strtolower($type) === 'independent') {
                $query->where('relation', 'like', '%independent%');
            } elseif (strtolower($type) === 'elite developer alliance') {
                $query->where(function ($q) {
                    $q->where('badge_type', 'elite')
                      ->orWhere('partner_developers', 'like', '%Emaar%');
                });
            }
        }

        $companies = $query
            ->withCount(['employees', 'units'])
            ->with([
                'employees' => fn ($q) => $q->select('id', 'company_id', 'user_id', 'avatar')->limit(3),
            ])
            ->paginate((int) $request->input('per_page', 10));

        return AgencyResource::collection($companies);
    }

    /**
     * عرض تفاصيل شركة واحدة مع علاقاتها.
     */
    public function show(Company $agency)
    {
        $agency->loadCount(['employees', 'units']);
        $agency->load([
            'employees' => fn ($q) => $q->select('id', 'company_id', 'user_id', 'avatar', 'position', 'department', 'status'),
            'units'     => fn ($q) => $q->with(['property:id,name,address', 'primaryImage'])->limit(20),
        ]);

        return new AgencyResource($agency);
    }
}
