<?php

namespace Kolydart\Laravel\App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Foundation\Application;
// Note: The Role model is imported dynamically to support different namespace configurations

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
 * 1. install the middleware:
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
 *    The default namespace is App\Role, but the middleware will try to determine the correct namespace.
 
 * Requirements:
 * - Role model with a 'permissions' relationship
 * - Permission model
 * - Many-to-many relationship between roles and permissions
 *
 * Performance:
 * The middleware caches permissions and roles mapping for 60 minutes, significantly reducing database queries.
 * The cache is automatically cleared when running:
 * php artisan cache:clear
 */
class AccessControl
{
    /**
     * Role model class name
     *
     * @var string
     */
    protected $roleModel;

    /**
     * Application instance
     *
     * @var \Illuminate\Contracts\Foundation\Application|null
     */
    protected $app;

    /**
     * Constructor
     *
     * @param \Illuminate\Contracts\Foundation\Application|null $app
     */
    public function __construct(?Application $app = null)
    {
        $this->app = $app ?: app();
        
        // Try to determine the Role model class name
        // Default to App\Role if the class exists
        if (class_exists('App\Role')) {
            $this->roleModel = 'App\Role';
        } elseif (class_exists('App\Models\Role')) {
            $this->roleModel = 'App\Models\Role';
        } else {
            // Fallback to a configurable value if available, or App\Role as last resort
            $this->roleModel = $this->app->make('config')->get('access_control.role_model', 'App\Role');
        }
    }

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

        // Use caching only in production
        if ($this->isProduction()) {
            $permissionsArray = Cache::remember('permissions_roles_map', 2 * 60 * 60, function () {
                return $this->getPermissionsArray();
            });
        } else {
            // Fetch without caching in non-production environments
            $permissionsArray = $this->getPermissionsArray();
        }

        foreach ($permissionsArray as $title => $roles) {
            Gate::define($title, function ($user) use ($roles) {
                return count(array_intersect($user->roles->pluck('id')->toArray(), $roles)) > 0;
            });
        }

        return $next($request);
    }

    /**
     * Fetches roles and permissions and formats them into an array.
     *
     * @return array
     */
    private function getPermissionsArray()
    {
        // Get the Role model class
        $roleModel = $this->roleModel;
        
        // Fetch roles with permissions
        $roles = $roleModel::with('permissions')->get();
        $permissionsArray = [];

        foreach ($roles as $role) {
            foreach ($role->permissions as $permissions) {
                $permissionsArray[$permissions->title][] = $role->id;
            }
        }

        return $permissionsArray;
    }

    /**
     * Check if application is in production environment.
     * This method is added for testability.
     *
     * @return bool
     */
    public function isProduction()
    {
        return $this->app->environment('production');
    }
}
