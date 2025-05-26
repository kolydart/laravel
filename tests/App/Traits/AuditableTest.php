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
}