<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class AuditableTest extends TestCase
{
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