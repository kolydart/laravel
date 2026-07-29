<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Kolydart\Laravel\App\Console\Commands\InstallAuthGatesCommand;
use Kolydart\Laravel\App\Console\Commands\MakeControllerTestCommand;
use Kolydart\Laravel\App\Console\Commands\GenerateErdCommand;

class KolydartServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/kolydart.php', 'kolydart'
        );

        // Register commands
        $this->commands([
            InstallAuthGatesCommand::class,
            MakeControllerTestCommand::class,
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

        $this->publishes([
            __DIR__.'/../config/kolydart.php' => config_path('kolydart.php'),
        ], 'config');

        // Register Impersonate listener
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \Kolydart\Laravel\App\Listeners\ImpersonateUser::class
        );

        // Register impersonate UI routes
        $routeConfig = config('kolydart.impersonate.routes', []);
        $middleware  = $routeConfig['middleware'] ?? ['auth'];
        $prefix      = $routeConfig['prefix'] ?? 'admin';
        $name        = $routeConfig['name'] ?? 'admin.';

        \Illuminate\Support\Facades\Route::middleware($middleware)
            ->prefix($prefix)
            ->name($name)
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/impersonate.php');
            });

        // The leave route is requested by the impersonated user, who typically
        // lacks backend access; it therefore accepts its own middleware stack.
        \Illuminate\Support\Facades\Route::middleware($routeConfig['leave_middleware'] ?? $middleware)
            ->prefix($prefix)
            ->name($name)
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/impersonate-leave.php');
            });

        // Load commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallAuthGatesCommand::class,
                MakeControllerTestCommand::class,
                GenerateErdCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/erd-generator.php' => config_path('erd-generator.php'),
            ], 'erd-generator-config');
        }

        // Other publishes...
    }
}
