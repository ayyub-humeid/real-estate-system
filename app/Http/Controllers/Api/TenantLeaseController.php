<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Http\Resources\LeaseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class TenantLeaseController extends Controller
{
    /**
     * Display a listing of the leases for the authenticated tenant.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();

        // Ensure the user has a tenant profile
        if (!$user->isTenant()) {
            return response()->json([
                'message' => 'User is not a tenant.'
            ], 403);
        }

        $tenants = \App\Models\Tenant::withoutGlobalScopes()->where('user_id', $user->id)->pluck('id');

        // Retrieve leases for the tenant across all profiles
        $leases = \App\Models\Lease::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenants)
            ->with([
                'unit:id,unit_number,property_id',
                'unit.property:id,name',
                'documents'
            ])
            ->orderBy('start_date', 'desc')
            ->paginate($request->input('per_page', 8));

        return LeaseResource::collection($leases);
    }

    /**
     * Display the specified lease.
     */
    public function show(Request $request, $id): LeaseResource|JsonResponse
    {
        $user = $request->user();

        if (!$user->isTenant()) {
            return response()->json([
                'message' => 'User is not a tenant.'
            ], 403);
        }

        $tenants = \App\Models\Tenant::withoutGlobalScopes()->where('user_id', $user->id)->pluck('id');

        $lease = \App\Models\Lease::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenants)
            ->with([
                'unit:id,unit_number,property_id',
                'unit.property:id,name',
                'documents'
            ])
            ->find($id);

        if (!$lease) {
            return response()->json([
                'message' => 'Lease not found or does not belong to the tenant.'
            ], 404);
        }

        return new LeaseResource($lease);
    }
}
