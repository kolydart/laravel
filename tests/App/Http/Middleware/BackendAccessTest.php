<?php

namespace Kolydart\Laravel\Tests\App\Http\Middleware;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\App\Http\Middleware\BackendAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;

class BackendAccessTest extends TestCase
{
    /** @test */
    public function it_exists()
    {
        $this->assertTrue(class_exists(BackendAccess::class));
    }

    // Additional tests would require a Laravel environment with auth
    // For example:
    //
    // /** @test */
    // public function it_redirects_unauthenticated_users_to_login()
    // {
    //     $middleware = new BackendAccess();
    //     $request = Request::create('/admin', 'GET');
    //
    //     // Mock Auth facade
    //     Auth::shouldReceive('check')->once()->andReturn(false);
    //
    //     $response = $middleware->handle($request, function() {});
    //
    //     $this->assertEquals(302, $response->getStatusCode());
    //     $this->assertEquals(route('login'), $response->headers->get('Location'));
    // }
}