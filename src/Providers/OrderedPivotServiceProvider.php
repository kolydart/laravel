<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Kolydart\Laravel\App\Console\Commands\MakeOrderedPivotMigration;

/**
 * Class OrderedPivotServiceProvider
 *
 * Service provider for the Ordered Pivot functionality.
 * Registers commands and publishes assets.
 *
 * @package Kolydart\Laravel\Providers
 */
class OrderedPivotServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register the command
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeOrderedPivotMigration::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish JavaScript assets. Sortable.min.js (1.15.6) is bundled so the
        // drag-to-reorder feature works offline, without a CDN dependency.
        $this->publishes([
            __DIR__ . '/../Resources/js/ordered-select.js' => public_path('vendor/kolydart/js/ordered-select.js'),
            __DIR__ . '/../Resources/js/vendor/Sortable.min.js' => public_path('vendor/kolydart/js/Sortable.min.js'),
        ], 'kolydart-ordered-pivot-js');

        // Publish Blade components
        $this->publishes([
            __DIR__ . '/../Resources/views/components' => resource_path('views/components/kolydart'),
        ], 'kolydart-ordered-pivot-views');

        // Load views from the package
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'kolydart');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            MakeOrderedPivotMigration::class,
        ];
    }
}
