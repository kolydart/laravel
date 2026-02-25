<?php

namespace Kolydart\Laravel\App\Http\Middleware;

use Closure;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $availableLocales = config('panel.available_languages', []);
        $language = null;

        // 1. Try to set language via URL parameter
        if ($request->has('change_language')) {
            $langParam = $request->input('change_language');
            if (array_key_exists($langParam, $availableLocales)) {
                $language = $langParam;
                session()->put('language', $language);
            }
        }

        // 2. Try to get language from Session
        if (!$language && session()->has('language')) {
            $langSession = session()->get('language');
            if (array_key_exists($langSession, $availableLocales)) {
                $language = $langSession;
            } else {
                session()->forget('language');
            }
        }

        // 3. Fallback to Primary Language
        $language = $language ?: config('panel.primary_language');

        // 4. Set Application Locale
        if ($language && array_key_exists($language, $availableLocales)) {
            app()->setLocale($language);
        }

        return $next($request);
    }
}
