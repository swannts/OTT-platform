<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * API Locale Middleware
 * 
 * This middleware handles localization specifically for Mobile App API requests.
 * It checks for the 'app-locale' header and sets the application locale accordingly.
 * 
 * - If 'app-locale' header is present → Use that locale (mobile app)
 * - If 'app-locale' header is absent → Don't change locale (frontend/web uses session)
 */
class ApiLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = null;

        // 1. Check custom mobile / frontend headers
        if ($request->hasHeader('app-locale')) {
            $locale = strtolower(trim($request->header('app-locale')));
        } elseif ($request->hasHeader('global-localization')) {
            $locale = strtolower(trim($request->header('global-localization')));
        } elseif ($request->hasHeader('X-Locale')) {
            $locale = strtolower(trim($request->header('X-Locale')));
        } elseif ($request->has('lang') || $request->has('locale')) {
            $locale = strtolower(trim($request->get('lang', $request->get('locale'))));
        } elseif ($request->hasHeader('Accept-Language')) {
            // e.g. "bn-BD,bn;q=0.9,en;q=0.8" or "bn"
            $acceptLang = $request->header('Accept-Language');
            $parts = explode(',', $acceptLang);
            if (!empty($parts)) {
                $primary = explode(';', $parts[0])[0];
                $code = strtolower(substr(trim($primary), 0, 2));
                if (in_array($code, ['en', 'bn'], true)) {
                    $locale = $code;
                }
            }
        }

        // Get available locales from config
        $availableLocales = array_keys(config('app.available_locales', ['en' => 'English (EN)', 'bn' => 'বাংলা (BN)']));

        if ($locale && in_array($locale, $availableLocales, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
