<?php

namespace Kolydart\Laravel\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

                return redirect()->route('login')->with('message', 'Impersonation expired');
            }
        }

        return $next($request);
    }
}
