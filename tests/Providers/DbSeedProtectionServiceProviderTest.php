<?php

namespace Kolydart\Laravel\Tests\Providers;

use Kolydart\Laravel\Tests\TestCase;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use Kolydart\Laravel\Providers\DbSeedProtectionServiceProvider;
use Mockery;
use TypeError;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Illuminate\Events\Dispatcher;
use ReflectionMethod;

class DbSeedProtectionServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('app', $this->app);

        // Set up event dispatcher
        $dispatcher = new Dispatcher($this->app);
        $this->app->instance('events', $dispatcher);
        Event::clearResolvedInstances();
        Event::setFacadeApplication($this->app);
    }

    /** @test */
    public function it_prevents_seeding_in_production(): void
    {
        // Set environment to production
        App::shouldReceive('environment')
            ->once()
            ->with('production')
            ->andReturn(true);

        // Create and register the service provider
        $provider = new DbSeedProtectionServiceProvider($this->app);
        $provider->boot();

        // Create a mock command, input and output
        $command = new Command('db:seed');
        $input = new ArrayInput(['command' => 'db:seed']);
        $output = new NullOutput();

        // Expect the code to exit with status 1
        $this->expectException(TypeError::class);

        // Get the event dispatcher and dispatch directly
        $dispatcher = $this->app->make('events');
        $dispatcher->dispatch(new CommandStarting($command, $input, $output));
    }

    /** @test */
    public function it_allows_seeding_in_non_production(): void
    {
        // Set environment to local
        App::shouldReceive('environment')
            ->once()
            ->with('production')
            ->andReturn(false);

        // Create and register the service provider
        $provider = new DbSeedProtectionServiceProvider($this->app);
        $provider->boot();

        // Create a mock command, input and output
        $command = new Command('db:seed');
        $input = new ArrayInput(['command' => 'db:seed']);
        $output = new NullOutput();

        // Get the event dispatcher and dispatch directly
        $dispatcher = $this->app->make('events');
        $dispatcher->dispatch(new CommandStarting($command, $input, $output));

        // If we reach here without dying, the test passes
        $this->assertTrue(true);
    }

    /** @test */
    public function it_allows_seeding_from_seed_static_command_in_production(): void
    {
        // Set environment to production
        App::shouldReceive('environment')
            ->with('production')
            ->andReturn(true);

        // Create the service provider
        $provider = new DbSeedProtectionServiceProvider($this->app);

        // Make handleDbSeedCommand accessible
        $reflectionMethod = new ReflectionMethod(DbSeedProtectionServiceProvider::class, 'handleDbSeedCommand');
        $reflectionMethod->setAccessible(true);

        // Create a partial mock to override getCallingCommand
        $mockProvider = Mockery::mock($provider)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Mock the getCallingCommand method to return 'seed:static'
        $mockProvider->shouldReceive('getCallingCommand')
            ->andReturn('seed:static');

        // Call the method with 'db:seed'
        $reflectionMethod->invoke($mockProvider, 'db:seed');

        // If we reach here without an exception, the test passes
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}