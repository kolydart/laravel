<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use PHPUnit\Framework\Attributes\Test;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\App\Traits\Uuidable;
use Illuminate\Database\Eloquent\Model;

class UuidableTest extends TestCase
{
    #[Test]
    public function it_exists()
    {
        $this->assertTrue(trait_exists(Uuidable::class));
    }

    // More specific tests would require a Laravel environment with database
}