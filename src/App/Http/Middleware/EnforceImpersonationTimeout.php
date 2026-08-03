<?php

namespace Kolydart\Laravel\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class EnforceImpersonationTimeout
{
    public function handle(Request $request, Closure $next)
    {
        $sessionKey = config('kolydart.impersonate.session_key', 'impersonating_admin_id');
        $stored     = session($sessionKey);

        if (is_array($stored) && isset($stored['started_at'])) {
            $ttl = (int) config('kolydart.impersonate.ttl_seconds', 3600);

            if ($ttl > 0 && (now()->timestamp - $stored['started_at']) > $ttl) {
                session()->forget($sessionKey);
                Auth::logout();

                // This middleware runs on the whole 'web' group, so it must not
                // assume a route named 'login' exists — falling back to '/' keeps
                // an app without one from turning an expiry into a 500.
                return Route::has('login')
                    ? redirect()->route('login')->with('message', 'Impersonation expired')
                    : redirect('/')->with('message', 'Impersonation expired');
            }
        }

        return $next($request);
    }
}
