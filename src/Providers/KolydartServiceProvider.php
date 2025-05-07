<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Kolydart\Laravel\App\Console\Commands\InstallAuthGatesCommand;

class KolydartServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register commands
        $this->commands([
            InstallAuthGatesCommand::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../App/Http/Middleware' => app_path('Http/Middleware'),
        ], 'middleware');

        // Load commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallAuthGatesCommand::class,
            ]);
        }

        // Other publishes...
    }
}
