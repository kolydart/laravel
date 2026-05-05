<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Kolydart\Laravel\App\Traits\Auditable;
use Kolydart\Laravel\Tests\TestCase;

class AuditableTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    private function makeIpResolver(): object
    {
        return new class extends Model {
            use Auditable;
            public static function resolveIp(): ?string
            {
                return static::getAuditLogIp();
            }
        };
    }
    // ── getAuditLogIp ──────────────────────────────────────────────────────

    /** @test */
    public function get_audit_log_ip_returns_null_when_store_ip_is_false(): void
    {
        Container::setInstance($this->app);
        $this->app->instance('config', new Config(['audit-log' => ['store_ip' => false]]));

        $this->assertNull($this->makeIpResolver()::resolveIp());
    }

    /** @test */
    public function get_audit_log_ip_returns_request_ip_when_store_ip_is_true(): void
    {
        Container::setInstance($this->app);
        $this->app->instance('config', new Config(['audit-log' => ['store_ip' => true]]));
        $this->app->instance('request', Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']));

        $this->assertSame('1.2.3.4', $this->makeIpResolver()::resolveIp());
    }

    /** @test */
    public function get_audit_log_ip_returns_request_ip_when_config_absent_defaulting_to_true(): void
    {
        Container::setInstance($this->app);
        $this->app->instance('config', new Config([]));
        $this->app->instance('request', Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '9.9.9.9']));

        $this->assertSame('9.9.9.9', $this->makeIpResolver()::resolveIp());
    }

    // ── structural ─────────────────────────────────────────────────────────

    /** @test */
    public function it_exists()
    {
        $this->assertTrue(trait_exists(Auditable::class));
    }

    /** @test */
    public function it_has_required_methods()
    {
        // Create a mock class using the trait
        $mock = new class extends Model {
            use Auditable;
        };

        // Test that the boot method exists
        $this->assertTrue(method_exists($mock, 'bootAuditable'));

        // Test that the audit method exists (it's protected, so we need reflection)
        $reflection = new \ReflectionClass($mock);
        $this->assertTrue($reflection->hasMethod('audit'));
    }

    /** @test */
    public function get_audit_log_model_returns_app_audit_log_when_models_namespace_does_not_exist()
    {
        $mock = new class extends Model {
            use Auditable;
            public static function resolveAuditLogModel(): string
            {
                return static::getAuditLogModel();
            }
        };

        // App\Models\AuditLog does not exist in the test environment
        $this->assertFalse(class_exists('App\Models\AuditLog'));
        $this->assertSame('App\AuditLog', $mock::resolveAuditLogModel());
    }

    /** @test */
    public function get_audit_log_model_returns_models_namespace_when_it_exists()
    {
        // Dynamically define App\Models\AuditLog for this test
        if (!class_exists('App\Models\AuditLog')) {
            eval('namespace App\Models; class AuditLog {}');
        }

        $mock = new class extends Model {
            use Auditable;
            public static function resolveAuditLogModel(): string
            {
                return static::getAuditLogModel();
            }
        };

        $this->assertSame('App\Models\AuditLog', $mock::resolveAuditLogModel());
    }
}