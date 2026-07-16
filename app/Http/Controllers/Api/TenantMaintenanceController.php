<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenanceRequest;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;

class TenantMaintenanceController extends Controller
{
    /**
     * قائمة طلبات الصيانة الخاصة بالمستأجر الحالي.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->isTenant()) {
            return response()->json(['message' => 'User is not a tenant.'], 403);
        }

        $tenant = $user->tenant;
        if (!$tenant || !$tenant->company) {
            return response()->json(['message' => 'Tenant company not found.'], 403);
        }

        if (!$tenant->company->hasFeature('maintenance_tracking')) {
            return response()->json([
                'success' => false,
                'message' => 'This feature is not available on your plan.'
            ], 403);
        }

        $unitIds = $tenant->leases()->pluck('unit_id');

        $requests = MaintenanceRequest::withoutGlobalScopes()
            ->whereIn('unit_id', $unitIds)
            ->with('images')
            ->latest()
            ->paginate($request->input('per_page', 10));

        return response()->json(['success' => true, 'data' => $requests]);
    }

    /**
     * إنشاء طلب صيانة جديد.
     */
    public function store(StoreMaintenanceRequest $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        if (!$tenant || !$tenant->company) {
            return response()->json(['message' => 'Tenant company not found.'], 403);
        }

        if (!$tenant->company->hasFeature('maintenance_tracking')) {
            return response()->json([
                'success' => false,
                'message' => 'This feature is not available on your plan.'
            ], 403);
        }

        // ✅ نقطة أمان حرجة: تأكيد إن unit_id فعلاً ضمن عقود المستأجر
        // (وليس أي unit_id عشوائي يرسله من الفرونت)
        $ownsUnit = $tenant->leases()
            ->where('unit_id', $request->unit_id)
            ->exists();

        if (!$ownsUnit) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to submit a request for this unit.'
            ], 403);
        }

        $maintenanceRequest = MaintenanceRequest::create([
            'unit_id'         => $request->unit_id,
            'reported_by_id'  => $user->id,
            'title'           => $request->title,
            'description'     => $request->description,
            'priority'        => $request->priority ?? 'medium',
            'status'          => MaintenanceRequest::STATUS_NEW,
            // company_id تنحقن تلقائياً من HasCompany trait إذا كان
            // company_id موجود على المستخدم، أو من boot() بالموديل عبر unit->property
        ]);

        // رفع الصور (اختياري) — نفس نمط Image::morphMany الموجود
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $file) {
                $path = $file->store('maintenance', 'public');
                $maintenanceRequest->images()->create([
                    'path'       => $path,
                    'disk'       => 'public',
                    'is_primary' => $index === 0,
                    'order'      => $index,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $maintenanceRequest->load('images'),
        ], 201);
    }
}
