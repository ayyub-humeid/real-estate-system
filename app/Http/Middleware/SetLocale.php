<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Priority: session → user preference → app default
        $locale = Session::get('locale');

        if (!$locale && auth()->check() && auth()->user()->locale) {
            $locale = auth()->user()->locale;
        }

        if (!$locale) {
            $locale = config('app.locale', 'ar');
        }

        if (!in_array($locale, ['ar', 'en'])) {
            $locale = 'ar';
        }

        App::setLocale($locale);
        \Illuminate\Support\Carbon::setLocale($locale);

        return $next($request);
    }
}
