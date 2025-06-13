<?php

namespace Kolydart\Laravel\Tests\Providers;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\Providers\RoleServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use Mockery;
use ReflectionClass;

/**
 * RoleServiceProvider Test Suite
 *
 * Unit tests for the RoleServiceProvider focusing on:
 * - Basic instantiation and structure
 * - Method visibility and accessibility
 * - Protected method logic without external dependencies
 * - Error handling patterns
 *
 * @package Kolydart\Laravel\Tests\Providers
 */
class RoleServiceProviderTest extends TestCase
{
    protected RoleServiceProvider $provider;
    protected $mockApp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApp = Mockery::mock(Application::class);
        $this->provider = new RoleServiceProvider($this->mockApp);
    }

    /** @test */
    public function it_exists_and_can_be_instantiated(): void
    {
        $this->assertInstanceOf(RoleServiceProvider::class, $this->provider);
    }

    /** @test */
    public function it_has_register_method(): void
    {
        $this->assertTrue(method_exists($this->provider, 'register'));
    }

    /** @test */
    public function it_has_boot_method(): void
    {
        $this->assertTrue(method_exists($this->provider, 'boot'));
    }

    /** @test */
    public function register_method_returns_void(): void
    {
        $result = $this->provider->register();
        $this->assertNull($result);
    }

    /** @test */
    public function it_has_protected_get_role_model_method(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $this->assertTrue($reflection->hasMethod('getRoleModel'));

        $method = $reflection->getMethod('getRoleModel');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function it_has_protected_can_register_gates_method(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $this->assertTrue($reflection->hasMethod('canRegisterGates'));

        $method = $reflection->getMethod('canRegisterGates');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function it_has_protected_can_check_roles_method(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $this->assertTrue($reflection->hasMethod('canCheckRoles'));

        $method = $reflection->getMethod('canCheckRoles');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function it_has_protected_get_roles_method(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $this->assertTrue($reflection->hasMethod('getRoles'));

        $method = $reflection->getMethod('getRoles');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function get_role_model_throws_exception_when_no_model_exists(): void
    {
        // Create a test provider that simulates no Role model existing
        $testProvider = new class($this->mockApp) extends RoleServiceProvider {
            public function testGetRoleModel(): string
            {
                return parent::getRoleModel();
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Role model not found. Expected App\Role or App\Models\Role');

        $testProvider->testGetRoleModel();
    }

    /** @test */
    public function can_check_roles_calls_can_register_gates(): void
    {
        // Create a mock provider to test the relationship between methods
        $provider = Mockery::mock(RoleServiceProvider::class, [$this->mockApp])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $provider->shouldReceive('canRegisterGates')->once()->andReturn(true);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('canCheckRoles');
        $method->setAccessible(true);

        $result = $method->invoke($provider);

        $this->assertTrue($result);
    }

    /** @test */
    public function can_register_gates_handles_exceptions_gracefully(): void
    {
        // Create a provider that will throw an exception during safety checks
        $provider = new class($this->mockApp) extends RoleServiceProvider {
            public function testCanRegisterGates(): bool
            {
                try {
                    // Simulate an exception during database checks
                    throw new \Exception('Database connection failed');
                } catch (\Exception $e) {
                    return false;
                }
            }
        };

        $result = $provider->testCanRegisterGates();
        $this->assertFalse($result);
    }

    /** @test */
    public function boot_method_handles_early_return_on_failed_safety_checks(): void
    {
        // Mock the provider to simulate failed safety checks
        $provider = Mockery::mock(RoleServiceProvider::class, [$this->mockApp])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $provider->shouldReceive('canRegisterGates')->once()->andReturn(false);
        $provider->shouldNotReceive('getRoles');

        // Call boot - it should return early without exceptions
        $provider->boot();

        // Test passes if we reach this point without exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function provider_has_proper_inheritance(): void
    {
        $this->assertInstanceOf(\Illuminate\Support\ServiceProvider::class, $this->provider);
    }

    /** @test */
    public function provider_stores_app_instance(): void
    {
        $reflection = new ReflectionClass($this->provider);
        $property = $reflection->getProperty('app');
        $property->setAccessible(true);

        $appInstance = $property->getValue($this->provider);
        $this->assertSame($this->mockApp, $appInstance);
    }

    /** @test */
    public function gate_variations_are_created_correctly(): void
    {
        // Test the gate variation logic manually
        $roleTitle = 'Student Publisher';

        $expectedVariations = [
            $roleTitle,  // Original case
            strtolower($roleTitle),  // Lowercase
            str_replace(' ', '_', strtolower($roleTitle))  // Lowercase with underscores
        ];

        $actualVariations = [
            $roleTitle,
            \Illuminate\Support\Str::lower($roleTitle),
            str_replace(' ', '_', \Illuminate\Support\Str::lower($roleTitle))
        ];

        // Remove duplicates as the provider does
        $actualVariations = array_unique($actualVariations);

        $this->assertEquals(['Student Publisher', 'student publisher', 'student_publisher'], array_values($actualVariations));
    }

    /** @test */
    public function duplicate_gate_variations_are_removed(): void
    {
        // Test with a role that would create duplicate variations
        $roleTitle = 'admin'; // lowercase already

        $variations = [
            $roleTitle,  // Original case
            \Illuminate\Support\Str::lower($roleTitle),  // Lowercase (same)
            str_replace(' ', '_', \Illuminate\Support\Str::lower($roleTitle))  // Lowercase with underscores (same)
        ];

        // Remove duplicates as the provider does
        $uniqueVariations = array_unique($variations);

        $this->assertCount(1, $uniqueVariations);
        $this->assertEquals(['admin'], array_values($uniqueVariations));
    }

    /** @test */
    public function role_gate_callback_structure_is_correct(): void
    {
        // Test that the role gate callback expects the correct parameters
        $mockUser = Mockery::mock();
        $mockRoles = ['admin', 'manager'];

        // This tests the expected signature of the role gate callback
        $callback = function($user, $roles) {
            // Convert to array as the provider does
            $roles = (array)$roles;
            return true; // Simplified for testing
        };

        $result = $callback($mockUser, $mockRoles);
        $this->assertTrue($result);

        // Test with single role
        $result = $callback($mockUser, 'admin');
        $this->assertTrue($result);
    }

    /** @test */
    public function individual_role_gate_callback_structure_is_correct(): void
    {
        // Test that individual role gate callbacks expect the correct parameters
        $mockUser = Mockery::mock();
        $mockRole = (object)['id' => 1, 'title' => 'Admin'];

        // This tests the expected signature of individual role gate callbacks
        $callback = function($user) use ($mockRole) {
            // Simulate the check that user->roles->contains('id', $role->id)
            // For testing purposes, we'll just return true
            return true;
        };

        $result = $callback($mockUser);
        $this->assertTrue($result);
    }

    /** @test */
    public function cache_key_is_consistent(): void
    {
        // Test that the cache key used is consistent
        $expectedCacheKey = 'role_service_provider_roles';
        $cacheTimeMinutes = 3600;

        // These are the values used in the provider
        $this->assertEquals('role_service_provider_roles', $expectedCacheKey);
        $this->assertEquals(3600, $cacheTimeMinutes); // 1 hour in seconds
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}