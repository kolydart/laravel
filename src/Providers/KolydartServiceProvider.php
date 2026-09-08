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

        $this->registerMediaRoutes();

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
     * Register the protected media routes and the @mediaUrl directive.
     *
     * The routes are opt-in via `kolydart.media.enabled` and are skipped when
     * spatie/laravel-medialibrary is absent — the package only suggests it. The
     * directive is not gated on either, deliberately: see below.
     */
    protected function registerMediaRoutes(): void
    {
        // Blade call sites must not reach for $media->getUrl(): a private disk
        // has no `url` key and the local driver answers /storage/{path} anyway,
        // so the link is dead rather than protected and nothing fails loudly.
        //
        // Registered unconditionally, because an unknown directive is not an
        // error in Blade — it renders as the literal text "@mediaUrl($media)" in
        // the page, silently. The documented migration order has the call sites
        // change before the disk does, so a Blade using @mediaUrl while
        // `enabled` is still false is an expected intermediate state, and it
        // must fail somewhere a developer will see. MediaUrl::url() answers
        // public collections without touching a route at all; for the rest it
        // throws RouteNotFoundException, which is the point.
        \Illuminate\Support\Facades\Blade::directive('mediaUrl', function ($expression) {
            return "<?php echo e(\\Kolydart\\Laravel\\App\\Support\\MediaUrl::url({$expression})); ?>";
        });

        if (! config('kolydart.media.enabled', false)) {
            return;
        }

        if (! class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) {
            return;
        }

        // The contract is the binding key and the concrete class the target, so
        // a resolver only has to implement one method — it does not have to
        // extend the default one, which is what the config documents.
        // `?:` rather than a config() default: Arr::get returns an explicitly
        // null value as-is, and bind() with a null target is a TypeError.
        $this->app->bind(
            \Kolydart\Laravel\App\Support\MediaAccessContract::class,
            config('kolydart.media.access') ?: \Kolydart\Laravel\App\Support\MediaAccess::class
        );

        $routeConfig = config('kolydart.media.routes', []);

        \Illuminate\Support\Facades\Route::middleware($routeConfig['middleware'] ?? ['web', 'auth'])
            ->prefix($routeConfig['prefix'] ?? 'admin')
            ->name($routeConfig['name'] ?? 'admin.')
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/media.php');
            });
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
