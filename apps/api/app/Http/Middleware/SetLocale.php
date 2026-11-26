<?php

namespace App\Http\Middleware;

use Closure;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $locale = strtolower(session()->get('locale', config('app.locale', 'en')));
        $availableLocales = array_keys(config('app.available_locales', ['en' => 'English (EN)']));
        if (! in_array($locale, $availableLocales, true)) {
            $locale = 'en';
        }

        session()->put('locale', $locale);
        app()->setLocale($locale);

        return $next($request);
    }
}
