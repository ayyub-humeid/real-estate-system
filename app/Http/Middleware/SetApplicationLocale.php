<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (in_array($locale, ['en', 'ar'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
