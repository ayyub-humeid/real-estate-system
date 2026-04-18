<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Bypass check for Super Admins or when logging out (Fixes the logout issue)
        if ($user->isSuperAdmin() || $request->routeIs('filament.admin.auth.logout')) {
            return $next($request);
        }

        $company = $user->company;

        // 2. Allow if no company is assigned
        if (! $company) {
            return $next($request);
        }

        // 3. Check Account Status and Subscription
        if (! $company->is_active || ! $company->hasActiveSubscription()) {
            return response()->view('errors.subscription-required', [
                'message' => $company->is_active 
                    ? 'Your subscription has expired or is inactive. Please upgrade your plan to continue.'
                    : 'Your company account is currently inactive. Please contact support.',
            ]);
        }

        return $next($request);
    }
}
