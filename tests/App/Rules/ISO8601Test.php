<?php

namespace Kolydart\Laravel\Tests\App\Rules;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\App\Rules\ISO8601;

class ISO8601Test extends TestCase
{
    /** @test */
    public function it_validates_valid_iso8601_dates()
    {
        $rule = new ISO8601();

        // Valid dates
        $this->assertTrue($rule->passes('date', '2023-01-01'));
        $this->assertTrue($rule->passes('date', '2020-02-29')); // Leap year
        $this->assertTrue($rule->passes('date', '1999-12-31'));
    }

    /** @test */
    public function it_rejects_invalid_iso8601_dates()
    {
        $rule = new ISO8601();

        // Invalid formats
        $this->assertFalse($rule->passes('date', '01-01-2023')); // Wrong format
        $this->assertFalse($rule->passes('date', '2023/01/01')); // Wrong separator
        $this->assertFalse($rule->passes('date', '20230101'));   // No separators

        // Invalid dates
        $this->assertFalse($rule->passes('date', '2023-13-01')); // Invalid month
        $this->assertFalse($rule->passes('date', '2023-01-32')); // Invalid day
        $this->assertFalse($rule->passes('date', '2021-02-29')); // Not a leap year

        // Non-string values
        $this->assertFalse($rule->passes('date', null));
        $this->assertFalse($rule->passes('date', 123));
        $this->assertFalse($rule->passes('date', []));
    }

    /** @test */
    public function it_has_an_error_message()
    {
        $rule = new ISO8601();
        $this->assertIsString($rule->message());
        $this->assertNotEmpty($rule->message());
    }
}