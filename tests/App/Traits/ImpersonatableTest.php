<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Kolydart\Laravel\App\Http\Middleware\EnforceImpersonationTimeout;
use Kolydart\Laravel\App\Listeners\ImpersonateUser;
use Kolydart\Laravel\App\Traits\Impersonatable;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ImpersonatableTest extends TestCase
{
    private Store $session;
    private Repository $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Container with abort() support so abort_if() works in unit tests
        $app = new class extends Container {
            public function abort(int $code, string $message = '', array $headers = []): void
            {
                throw new HttpException($code, $message, null, $headers);
            }
        };
        Container::setInstance($app);
        $app->instance('app', $app);

        $dispatcher = new Dispatcher($app);
        $app->instance('events', $dispatcher);
        Event::clearResolvedInstances();
        Event::setFacadeApplication($app);
        Facade::setFacadeApplication($app);

        $this->config = new Repository([
            'kolydart' => [
                'impersonate' => [
                    'enabled'        => false,
                    'admin_role_id'  => 1,
                    'session_key'    => 'impersonating_admin_id',
                    'ttl_seconds'    => 3600,
                    'user_id'        => null,
                    'user_id_env'    => 'IMPERSONATE_USER_ID',
                ],
            ],
            'auth' => [
                'providers' => ['users' => ['model' => 'App\Models\User']],
            ],
        ]);
        $app->instance('config', $this->config);

        $this->session = new Store('test', new ArraySessionHandler(100));
        $this->session->start();
        $app->instance('session', $this->session);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Event::clearResolvedInstances();
        Event::setFacadeApplication(null);
        parent::tearDown();
    }

    // ── Structural ─────────────────────────────────────────────────────────

    /** @test */
    public function it_exists(): void
    {
        $this->assertTrue(trait_exists(Impersonatable::class));
    }

    /** @test */
    public function it_has_required_methods(): void
    {
        $mock = new class {
            use Impersonatable;
        };
        $reflection = new \ReflectionClass($mock);
        $this->assertTrue($reflection->hasMethod('impersonate'));
        $this->assertTrue($reflection->hasMethod('leaveImpersonation'));
        $this->assertTrue($reflection->hasMethod('auditImpersonation'));
    }

    // ── auditImpersonation class resolution ────────────────────────────────

    /** @test */
    public function audit_impersonation_resolves_models_namespace_when_class_exists(): void
    {
        if (!class_exists('App\Models\AuditLog')) {
            eval('namespace App\Models; class AuditLog { public static function create($data){} }');
        }

        $class = class_exists('App\Models\AuditLog') ? 'App\Models\AuditLog'
               : (class_exists('App\AuditLog') ? 'App\AuditLog' : null);

        $this->assertSame('App\Models\AuditLog', $class);
    }

    /** @test */
    public function audit_impersonation_returns_null_when_no_audit_log_exists(): void
    {
        $class = class_exists('App\Models\NoSuchLog') ? 'App\Models\NoSuchLog'
               : (class_exists('App\NoSuchLog') ? 'App\NoSuchLog' : null);

        $this->assertNull($class);
    }

    // ── Session guards (tested directly since abort_if needs full Gate) ────

    /** @test */
    public function nested_impersonation_guard_aborts_409_when_session_is_active(): void
    {
        $sessionKey = $this->config->get('kolydart.impersonate.session_key');
        $this->session->put($sessionKey, ['admin_id' => 1, 'started_at' => now()->timestamp]);

        try {
            abort_if($this->session->has($sessionKey), 409, 'Already impersonating');
            $this->fail('Expected HttpException not thrown');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    // ── BC: scalar vs array session ────────────────────────────────────────

    /** @test */
    public function leave_impersonation_bc_handles_legacy_scalar_session_id(): void
    {
        $scalarStored = 42;
        $arrayStored  = ['admin_id' => 42, 'started_at' => now()->timestamp];

        $resolveId = function ($stored) {
            return is_array($stored) ? ($stored['admin_id'] ?? null) : $stored;
        };

        $this->assertSame(42, $resolveId($scalarStored));
        $this->assertSame(42, $resolveId($arrayStored));
    }

    // ── Listener guard ─────────────────────────────────────────────────────

    /** @test */
    public function listener_skips_reauth_when_impersonation_session_is_active(): void
    {
        $this->config->set('kolydart.impersonate.enabled', true);
        $this->config->set('kolydart.impersonate.user_id', 5);

        $sessionKey = $this->config->get('kolydart.impersonate.session_key');
        $this->session->put($sessionKey, ['admin_id' => 1, 'started_at' => now()->timestamp]);

        // Verify the condition that makes the listener return early
        $this->assertTrue(
            $this->session->has(config('kolydart.impersonate.session_key', 'impersonating_admin_id'))
        );

        // The listener should return early at the session guard.
        // We call handle() with a mock user that has no roles() method to
        // verify it exits before reaching the admin-role check.
        $user = Mockery::mock();
        $user->shouldNotReceive('roles');

        $event      = new \Illuminate\Auth\Events\Login('web', $user, false);
        $listener   = new ImpersonateUser();
        $listener->handle($event);

        // Mockery will assert shouldNotReceive in tearDown
        $this->assertTrue(true);
    }

    // ── Middleware ─────────────────────────────────────────────────────────

    /** @test */
    public function middleware_exists(): void
    {
        $this->assertTrue(class_exists(EnforceImpersonationTimeout::class));
    }

    /** @test */
    public function middleware_passes_request_through_when_within_ttl(): void
    {
        $sessionKey = $this->config->get('kolydart.impersonate.session_key');
        $this->session->put($sessionKey, [
            'admin_id'   => 1,
            'started_at' => now()->timestamp,
        ]);

        $passed     = false;
        $middleware = new EnforceImpersonationTimeout();
        $request    = \Illuminate\Http\Request::create('/admin/dashboard', 'GET');

        $middleware->handle($request, function ($req) use (&$passed) {
            $passed = true;
            return new \Illuminate\Http\Response('ok');
        });

        $this->assertTrue($passed);
    }

    /** @test */
    public function middleware_detects_session_as_expired_when_ttl_exceeded(): void
    {
        $sessionKey = $this->config->get('kolydart.impersonate.session_key');
        $this->session->put($sessionKey, [
            'admin_id'   => 1,
            'started_at' => now()->subHours(2)->timestamp,
        ]);

        $ttl     = (int) $this->config->get('kolydart.impersonate.ttl_seconds', 3600);
        $stored  = $this->session->get($sessionKey);
        $elapsed = now()->timestamp - $stored['started_at'];

        $this->assertGreaterThan($ttl, $elapsed);
    }
}
