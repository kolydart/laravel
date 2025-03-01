<?php

namespace Kolydart\Laravel\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

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
        if (method_exists(auth()->user(), 'has_backend_access') && !auth()->user()->has_backend_access()) {

            abort(Response::HTTP_FORBIDDEN, '403 Forbidden');

        }

        return $next($request);

    }

} 