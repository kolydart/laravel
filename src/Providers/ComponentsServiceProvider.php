<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

/**
 * Service provider for registering Blade components.
 * 
 
 */
class ComponentsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register components
        Blade::componentNamespace('Kolydart\\Laravel\\App\\View\\Components', 'kolydart');
    }

    public function register()
    {
        //
    }
} 