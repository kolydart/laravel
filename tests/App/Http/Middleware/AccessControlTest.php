<?php

namespace Kolydart\Laravel\Tests\App\Http\Middleware;

use Kolydart\Laravel\App\Http\Middleware\AccessControl;
use Kolydart\Laravel\Tests\TestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Config\Repository as ConfigRepository;
use Mockery;
use ReflectionClass;

class AccessControlTest extends TestCase
{
    /** @test */
    public function it_exists()
    {
        $this->assertTrue(class_exists(AccessControl::class));
    }

    /** @test */
    public function it_constructs_with_role_model_detection()
    {
        // Create a mock application
        $app = Mockery::mock(Application::class);
        $config = Mockery::mock(ConfigRepository::class);
        
        $app->shouldReceive('make')->with('config')->andReturn($config);
        $config->shouldReceive('get')->with('access_control.role_model', 'App\Role')->andReturn('App\Role');
        
        // Create the middleware with the mock application
        $middleware = new AccessControl($app);
        $this->assertInstanceOf(AccessControl::class, $middleware);
        
        // Use reflection to verify that roleModel property exists
        $reflection = new ReflectionClass($middleware);
        $this->assertTrue($reflection->hasProperty('roleModel'));
    }

    /** @test */
    public function it_dynamically_determines_role_model_class()
    {
        // Create a mock application
        $app = Mockery::mock(Application::class);
        $config = Mockery::mock(ConfigRepository::class);
        
        $app->shouldReceive('make')->with('config')->andReturn($config);
        $config->shouldReceive('get')->with('access_control.role_model', 'App\Role')->andReturn('App\Models\Role');
        
        // Create the middleware with the mock application
        $middleware = new AccessControl($app);
        
        // Use reflection to check the roleModel property
        $reflection = new ReflectionClass($middleware);
        $property = $reflection->getProperty('roleModel');
        $property->setAccessible(true);
        
        // Verify it's set to the expected model class
        $roleModelClass = $property->getValue($middleware);
        $this->assertEquals('App\Models\Role', $roleModelClass);
    }

    /** @test */
    public function it_uses_cache_only_in_production()
    {
        // Create a mock application - production environment
        $appProd = Mockery::mock(Application::class);
        $configProd = Mockery::mock(ConfigRepository::class);
        
        $appProd->shouldReceive('make')->with('config')->andReturn($configProd);
        $configProd->shouldReceive('get')->withAnyArgs()->andReturn('App\Role');
        $appProd->shouldReceive('environment')->with('production')->andReturn(true);
        
        // Create the middleware with production environment
        $middlewareProd = new AccessControl($appProd);
        $this->assertTrue($middlewareProd->isProduction());
        
        // Create a mock application - non-production environment
        $appDev = Mockery::mock(Application::class);
        $configDev = Mockery::mock(ConfigRepository::class);
        
        $appDev->shouldReceive('make')->with('config')->andReturn($configDev);
        $configDev->shouldReceive('get')->withAnyArgs()->andReturn('App\Role');
        $appDev->shouldReceive('environment')->with('production')->andReturn(false);
        
        // Create the middleware with non-production environment
        $middlewareDev = new AccessControl($appDev);
        $this->assertFalse($middlewareDev->isProduction());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
} 