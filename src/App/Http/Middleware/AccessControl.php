<?php

namespace Kolydart\Laravel\App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
// Note: The Role model should be imported from your application's namespace
// Adjust this import based on your application's structure
use App\Role;

/**
 * AccessControl Middleware
 *
 * Provides role-based access control with permission caching for Laravel applications.
 *
 * Features:
 * - Role-based access control (RBAC) system
 * - Permission caching (60 minutes by default)
 * - Support for users with multiple roles
 * - Guest user permission handling
 *
 * Installation:
 * 1. Publish the middleware:
 *    php artisan vendor:publish --provider="Kolydart\Laravel\Providers\KolydartServiceProvider" --tag="middleware"
 *
 * 2. Register the middleware in app/Http/Kernel.php:
 *    protected $middlewareGroups = [
 *        'web' => [
 *            // ...
 *            \App\Http\Middleware\AuthGates::class,
 *        ],
 *        'api' => [
 *            // ...
 *            \App\Http\Middleware\AuthGates::class,
 *        ],
 *    ];
 *
 * 3. Ensure your application has the appropriate Role and Permission models defined.
 *    The default namespace is App\Role, but you may need to adjust the import statement
 *    at the top of this file to match your application's structure.
 * NEW:
 * ### Installation

 * You can install the middleware in two ways:
 * 
 * #### Option 1: Using the CLI Command (Recommended)
 * 
 * ```bash
 * php artisan kolydart:install-auth-gates
 * ```
 * 
 * This command will:
 * - Create the middleware file at `app/Http/Middleware/AuthGates.php`
 * - Set up the proper inheritance from the package
 * 
 * To force overwrite an existing file:
 * 
 * ```bash
 * php artisan kolydart:install-auth-gates --force
 * ```
 * 
 * #### Option 2: Using the Publish Command
 * 
 * ```bash
 * php artisan vendor:publish --provider="Kolydart\Laravel\Providers\KolydartServiceProvider" --tag="middleware"
 * ```
 * 
 * ### Register the Middleware
 * 
 * After installation, register the middleware in `app/Http/Kernel.php`:
 * 
 * ```php
 * protected $middlewareGroups = [
     * 'web' => [
         * // ...
         * \App\Http\Middleware\AuthGates::class,
     * ],
     * 'api' => [
         * // ...
         * \App\Http\Middleware\AuthGates::class,
     * ],
 * ];
 * ```

 * Requirements:
 * - Role model with a 'permissions' relationship
 * - Permission model
 * - Many-to-many relationship between roles and permissions
 *
 * Performance:
 * The middleware caches permissions and roles mapping for 60 minutes, significantly reducing database queries.
 *
 * To clear the cache manually:
 * \Illuminate\Support\Facades\Cache::forget('permissions_roles_map');
 *
 * The cache is automatically cleared when running:
 * php artisan cache:clear
 */
class AccessControl
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
        $user = auth()->user();

        if (! $user) {
            return $next($request);
        }

        // Cache roles and permissions for 60 minutes
        $permissionsArray = Cache::remember('permissions_roles_map', 60 * 60, function () {
            // Note: This requires a Role model with 'permissions' relationship
            $roles = Role::with('permissions')->get();
            $permissionsArray = [];

            foreach ($roles as $role) {
                foreach ($role->permissions as $permissions) {
                    $permissionsArray[$permissions->title][] = $role->id;
                }
            }

            return $permissionsArray;
        });

        foreach ($permissionsArray as $title => $roles) {
            Gate::define($title, function ($user = null) use ($roles) {
                
                // CAUTION; guest user
                if (is_null($user)) {
                    $guestRole = Role::where('title', 'Guest')->first();
                    return $guestRole && in_array($guestRole->id, $roles);
                }

                return count(array_intersect($user->roles->pluck('id')->toArray(), $roles)) > 0;
            });
        }

        return $next($request);
    }
}
