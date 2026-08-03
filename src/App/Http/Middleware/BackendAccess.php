<?php

namespace Kolydart\Laravel\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to restrict access to backend routes
 */
class BackendAccess
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Deny by default. The previous form (method_exists(auth()->user(), …) && …)
        // let a request through whenever the left operand was false — i.e. for a
        // guest, and for any user model that does not implement the check at all —
        // which is exactly when access must be refused.
        if (!$user
            || !method_exists($user, 'has_backend_access')
            || !$user->has_backend_access()) {

            abort(Response::HTTP_FORBIDDEN, '403 Forbidden');

        }

        return $next($request);

    }

}