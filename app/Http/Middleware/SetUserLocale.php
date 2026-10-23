<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /** @var array<int, string> */
    private const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        // The cookie mirrors the browser/localStorage selection and lets the
        // first server render use it. Session and account settings are
        // fallbacks for browsers that do not yet have this preference.
        $locale = $request->cookie('madar_locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = $request->session()->get('locale');
        }

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = $request->user()?->locale;
        }

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
