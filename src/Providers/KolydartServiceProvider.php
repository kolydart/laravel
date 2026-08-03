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

        $this->registerImpersonationTimeout();

        // Register impersonate UI routes
        $routeConfig = config('kolydart.impersonate.routes', []);

        // The fallback must include 'web'. mergeConfigFrom() merges only the top
        // level, so an app that published config/kolydart.php before the 'routes'
        // key existed loses the whole sub-array and lands here — a POST route that
        // grants a session swap must never run without VerifyCsrfToken.
        $middleware  = $routeConfig['middleware'] ?? ['web', 'auth'];
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

    /**
     * Append EnforceImpersonationTimeout to the 'web' middleware group.
     *
     * Previously this was a manual step, documented as publishing the middleware
     * and registering \App\Http\Middleware\EnforceImpersonationTimeout::class.
     * That never worked: `vendor:publish --tag=middleware` copies the file
     * verbatim, so the published copy still declares the package namespace and
     * the App\ class the Kernel referenced did not exist. The practical result
     * was that `ttl_seconds` was documented but never enforced.
     *
     * Registering it here makes the TTL effective out of the box. The middleware
     * is inert for every request that carries no impersonation session key, so
     * apps that do not use impersonation are unaffected.
     *
     * Set `kolydart.impersonate.auto_register_timeout` to false to opt out and
     * register it manually instead.
     */
    protected function registerImpersonationTimeout(): void
    {
        if (! config('kolydart.impersonate.auto_register_timeout', true)) {
            return;
        }

        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        // appendMiddlewareToGroup() is a concrete-Kernel API; a custom Kernel
        // implementing only the contract must register the middleware itself.
        if (! method_exists($kernel, 'appendMiddlewareToGroup')) {
            return;
        }

        $kernel->appendMiddlewareToGroup(
            'web',
            \Kolydart\Laravel\App\Http\Middleware\EnforceImpersonationTimeout::class
        );
    }
}
