<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\ServiceProvider;

class KolydartServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../App/Http/Middleware' => app_path('Http/Middleware'),
        ], 'middleware');

        // Other publishes...
    }
}
