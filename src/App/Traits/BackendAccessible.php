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
     */
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