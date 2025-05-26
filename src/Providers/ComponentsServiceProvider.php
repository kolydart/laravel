<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

/**
 * Service provider for registering Blade components.
 *
 * Components are registered for Laravel 8.0 and above.
 */
class ComponentsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Only register components for Laravel 8+
        if (version_compare($this->app->version(), '8.0.0', '>=')) {
            // Register components using the correct component namespace
            Blade::componentNamespace('Kolydart\\Laravel\\App\\View\\Components', 'kolydart');
        }
    }

    public function register()
    {
        //
    }
}
