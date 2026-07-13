<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class TenantPaymentController extends Controller
{
    /**
     * Display a listing of the payments for the authenticated tenant.
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

        $tenant = $user->tenant;

        // Retrieve payments through the tenant's leases
        $payments = $tenant->payments()
            ->with([
                'lease' => function ($query) {
                    $query->select('id', 'unit_id', 'start_date', 'end_date', 'status')
                        ->with(['unit:id,unit_number,property_id', 'unit.property:id,name']);
                }
            ])
            ->orderBy('due_date', 'desc')
            ->paginate($request->input('per_page', 8));

        return PaymentResource::collection($payments);
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, $id): PaymentResource|JsonResponse
    {
        $user = $request->user();

        if (!$user->isTenant()) {
            return response()->json([
                'message' => 'User is not a tenant.'
            ], 403);
        }

        $tenant = $user->tenant;

        $payment = $tenant->payments()
            ->with([
                'lease' => function ($query) {
                    $query->select('id', 'unit_id', 'start_date', 'end_date', 'status')
                        ->with(['unit:id,unit_number,property_id', 'unit.property:id,name']);
                }
            ])
            ->find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found or does not belong to the tenant.'
            ], 404);
        }

        return new PaymentResource($payment);
    }
}
