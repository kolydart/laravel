<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\App\Traits\Uuidable;
use Illuminate\Database\Eloquent\Model;

class UuidableTest extends TestCase
{
    /** @test */
    public function it_exists()
    {
        $this->assertTrue(trait_exists(Uuidable::class));
    }

    // More specific tests would require a Laravel environment with database
}