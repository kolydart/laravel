<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Kolydart\Laravel\App\Traits\Impersonatable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Redirect resolution for UI-based impersonation.
 *
 * These tests pin the backward-compatibility contract for apps that published
 * config/kolydart.php before 'redirect_to' / 'redirect_back_to' /
 * 'routes.leave_middleware' existed: their config arrays lack those keys and
 * must keep behaving exactly as before.
 */
class ImpersonatableRedirectTest extends TestCase
{
    private Repository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        Container::setInstance($app);
        $app->instance('app', $app);

        // Legacy published config: none of the new keys are present
        $this->config = new Repository([
            'kolydart' => [
                'impersonate' => [
                    'enabled'       => false,
                    'admin_role_id' => 1,
                    'session_key'   => 'impersonating_admin_id',
                    'ttl_seconds'   => 3600,
                    'routes'        => [
                        'middleware' => ['web', 'auth', 'backend'],
                        'prefix'     => 'admin',
                        'name'       => 'admin.',
                    ],
                ],
            ],
        ]);
        $app->instance('config', $this->config);

        $router = new class {
            public array $names = ['admin.home', 'frontend.home', 'admin.users.index'];

            public function has(string $name): bool
            {
                return in_array($name, $this->names, true);
            }
        };
        $app->instance('router', $router);

        $url = new class {
            public function route(string $name, $parameters = [], bool $absolute = true): string
            {
                return 'https://app.test/route/' . $name;
            }

            public function to(string $path, $parameters = [], $secure = null): string
            {
                return 'https://app.test' . $path;
            }
        };
        $app->instance('url', $url);
        $app->instance(UrlGenerator::class, $url);

        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    private function subject(): object
    {
        return new class {
            use Impersonatable;

            public function startUrl($user): string
            {
                return $this->impersonationStartUrl($user);
            }

            public function routeUrl(?string $route): string
            {
                return $this->impersonationRouteUrl($route);
            }
        };
    }

    // ── Backward compatibility ─────────────────────────────────────────────

    #[Test]
    public function user_model_without_backend_access_method_lands_on_admin_home(): void
    {
        $user = new class {};

        $this->assertSame(
            'https://app.test/route/admin.home',
            $this->subject()->startUrl($user)
        );
    }

    #[Test]
    public function user_with_backend_access_lands_on_admin_home(): void
    {
        $user = new class {
            public function has_backend_access(): bool
            {
                return true;
            }
        };

        $this->assertSame(
            'https://app.test/route/admin.home',
            $this->subject()->startUrl($user)
        );
    }

    #[Test]
    public function leave_redirect_defaults_to_admin_users_index_when_config_key_is_absent(): void
    {
        $this->assertNull($this->config->get('kolydart.impersonate.redirect_back_to'));

        $this->assertSame(
            'https://app.test/route/admin.users.index',
            $this->subject()->routeUrl(
                $this->config->get('kolydart.impersonate.redirect_back_to', 'admin.users.index')
            )
        );
    }

    #[Test]
    public function leave_middleware_falls_back_to_the_shared_stack_when_absent(): void
    {
        $routes     = $this->config->get('kolydart.impersonate.routes');
        $middleware = $routes['middleware'];

        $this->assertArrayNotHasKey('leave_middleware', $routes);
        $this->assertSame($middleware, $routes['leave_middleware'] ?? $middleware);
    }

    // ── New behaviour ──────────────────────────────────────────────────────

    #[Test]
    public function user_without_backend_access_lands_on_frontend_home(): void
    {
        $user = new class {
            public function has_backend_access(): bool
            {
                return false;
            }
        };

        $this->assertSame(
            'https://app.test/route/frontend.home',
            $this->subject()->startUrl($user)
        );
    }

    #[Test]
    public function configured_redirect_route_wins_over_auto_detection(): void
    {
        $this->config->set('kolydart.impersonate.redirect_to', 'admin.users.index');

        $user = new class {
            public function has_backend_access(): bool
            {
                return false;
            }
        };

        $this->assertSame(
            'https://app.test/route/admin.users.index',
            $this->subject()->startUrl($user)
        );
    }

    #[Test]
    public function unknown_route_names_fall_back_to_the_site_root(): void
    {
        $this->assertSame('https://app.test/', $this->subject()->routeUrl('no.such.route'));
        $this->assertSame('https://app.test/', $this->subject()->routeUrl(null));
    }

    // ── Shipped defaults ───────────────────────────────────────────────────

    #[Test]
    public function shipped_config_defaults_preserve_previous_behaviour(): void
    {
        $config = require __DIR__ . '/../../../src/config/kolydart.php';

        $this->assertNull($config['impersonate']['redirect_to']);
        $this->assertNull($config['impersonate']['routes']['leave_middleware']);
        $this->assertSame('admin.users.index', $config['impersonate']['redirect_back_to']);
        $this->assertSame(['web', 'auth', '2fa', 'backend'], $config['impersonate']['routes']['middleware']);
    }
}
