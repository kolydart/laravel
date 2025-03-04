<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Kolydart\Laravel\Resources\Views\Components\FormFieldsSize;

/**
 * Service provider for registering Blade components.
 * 
 
 */
class ComponentsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register components
        Blade::componentNamespace('Kolydart\\Laravel\\Resources\\Views\\Components', 'kolydart');
    }

    public function register()
    {
        //
    }
} 