<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Support\Facades\Route;

/**
 * Trait for determining if a user has backend access and handling related functionality
 * @see frontend / backend workflow
 */
trait BackendAccessible
{
    /**
     * Is this user authorized to view backend
     * @example
     * if (!\App\User::has_backend_access()) {
     *     abort(403);
     * }
     * @return boolean
     */
    public static function has_backend_access()
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->roles->count() == 0) {
            return false;
        }

        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                if ($permission->title == 'backend_access') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return the appropriate home route based on user access
     * @return string
     */
    public static function home()
    {
        if (self::has_backend_access()) {
            return route('admin.home', [], false);
        } else {
            return route('frontend.home', [], false);
        }
    }

    /**
     * Check if current route is in admin group
     * @return bool
     * @note Do not test negative (!User::route_group_is_admin)
     *       in some cases there is no route (tests, factories, etc)
     * @todo consider switching to namespace-driven approach
     *
     *  From chat with claude:
     *
     *  Why Namespace Checking Works Well Here:
     *
     *  1. Clear Convention in the Project Structure
     *
     *  The project follows Laravel's standard convention:
     *  - Admin controllers: App\Http\Controllers\Admin\*
     *  - Frontend controllers: App\Http\Controllers\Frontend\*
     *  - This is a deliberate architectural decision that separates concerns
     *
     *  2. Compile-Time Safety
     *
     *  - Namespace is part of the class definition - it can't change at runtime
     *  - It's determined when the class is loaded, not dependent on request state
     *  - No risk of manipulation through user input
     *
     *  3. Works in All Contexts
     *
     *  - Works in web requests, console commands, tests, queued jobs
     *  - Doesn't depend on having a current HTTP request
     *  - Doesn't break in unit tests where routes might not be defined
     *
     *  4. Performance
     *
     *  - get_class() and str_contains() are very fast operations
     *  - No database queries or complex logic needed
     *  - No dependency injection required
     *
     *  Alternative Approaches and Their Drawbacks:
     *
     *  Route-Based Detection:
     *
     *  $routeName = request()->route()?->getName();
     *  return str_starts_with($routeName, 'admin.');
     *  Issues:
     *  - Fails in tests without a route
     *  - Fails in console commands
     *  - Depends on route naming conventions being maintained
     *  - Requires an active request context
     *
     *  Middleware/Guard Checking:
     *
     *  return auth()->guard('admin')->check();
     *  Issues:
     *  - Assumes admin users only access admin routes
     *  - Fails for unauthenticated admin pages
     *  - Mixes authentication with context detection
     *
     *  URL Path Checking:
     *
     *  return request()->is('admin/*');
     *  Issues:
     *  - Fragile - breaks if URL structure changes
     *  - Doesn't work in API contexts
     *  - Requires active HTTP request
     *
     *  Configuration/Property Based:
     *
     *  protected $context = 'admin'; // in controller
     *  Issues:
     *  - Requires manual setting in each controller
     *  - Easy to forget or misconfigure
     *  - More boilerplate code
     *
     *  Why This is the Right Choice for This Codebase:
     *
     *  1. The namespace IS the context - Admin controllers are intentionally in the Admin namespace
     *  2. It's self-documenting - You can tell a controller's context just by looking at its namespace
     *3. It's testable - The test file can mock this easily by having test helpers in different namespaces
     *  4. It follows Laravel conventions - This is how Laravel itself often determines context
     *
     *The BackendAccessible trait you mentioned is likely for user authentication/authorization (determining if a
     *user can access backend), not for determining which controller context we're in. These are different concerns:
     *  - BackendAccessible: "Can this USER access admin features?"
     *  - determineContext(): "Which FORM VARIANT should this controller use?"
     *     */
    public static function route_group_is_admin(): bool
    {
        if (!request()->route()) {
            return false;
        }

        return str(Route::currentRouteName())->before('.') == 'admin';
    }

    /**
     * Check if current route is in frontend group
     * @return bool
     * @note Do not test negative (!User::route_group_is_frontend)
     *       in some cases there is no route (tests, factories, etc)
     */
    public static function route_group_is_frontend(): bool
    {
        if (!request()->route()) {
            return false;
        }

        return str(Route::currentRouteName())->before('.') == 'frontend';
    }
}
