<?php

namespace Kolydart\Laravel\App\Helpers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Helper class for Laravel routing operations
 */
class RouterHelper
{
    /**
     * Auto create permission title for method
     *
     * @example display 'edit' route in Agent 'show' view
     *          RouterHelper::getPermissionTitle()
     *          returns 'agent_edit'
     * @param  string|null $methodName default: edit
     * @param  string|null $routeName  default: Route::currentRouteName
     * @return string      example: agent_edit
     */
    public static function getPermissionTitle(?string $methodName = null, ?string $routeName = null): string
    {
        if ($methodName === null) {
            $methodName = 'edit';
        }

        if ($routeName === null) {
            $routeName = Route::currentRouteName();
        }

        // Remove current method name (.show)
        $result = Str::beforeLast($routeName, '.');

        // Get all after 'admin.|frontend.'
        $result = Str::afterLast($result, '.');

        // Convert to singular
        $result = Str::singular($result);

        // Add method name
        $result .= "_{$methodName}";

        // Replace hyphens with underscores (content-page.edit => content_page_edit)
        $result = str_replace('-', '_', $result);

        return $result;
    }

    /**
     * Replace method (last segment) in router name
     *
     * @example RouterHelper::replaceMethodInRouterName()
     *          returns 'admin.agents.edit' when current route is 'admin.agents.show'
     * @param  string|null $methodName new router name
     * @param  string|null $routeName  route to be altered
     * @return string      modified route name
     */
    public static function replaceMethodInRouterName($methodName = null, $routeName = null): string
    {
        if ($methodName === null) {
            $methodName = 'edit';
        }

        if ($routeName === null) {
            $routeName = Route::currentRouteName();
        }

        $result = Str::beforeLast($routeName, '.') . ".$methodName";

        return $result;
    }
}