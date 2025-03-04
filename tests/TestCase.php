<?php

namespace Kolydart\Laravel\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;

abstract class TestCase extends BaseTestCase
{
    protected Container $app;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app = new Container;
        $this->app->instance('app', $this->app);
        
        // Set up event dispatcher
        $dispatcher = new Dispatcher($this->app);
        $this->app->instance('events', $dispatcher);
        Event::clearResolvedInstances();
        Event::setFacadeApplication($this->app);
        
        Facade::setFacadeApplication($this->app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Event::clearResolvedInstances();
        Event::setFacadeApplication(null);
        
        parent::tearDown();
    }
} 