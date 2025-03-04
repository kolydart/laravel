<?php

namespace Tests\App\Helpers;

use PHPUnit\Framework\TestCase;
use Kolydart\Laravel\App\Helpers\CalendarHelper;

class CalendarHelperTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_convert_gregorian_to_julian_date()
    {
        $gregorianDate = '2024-03-20';
        $julianDate = CalendarHelper::ISO8601toJD($gregorianDate);
        
        // The expected Julian Day number for March 20, 2024
        $this->assertEquals(2460390, $julianDate);
    }

    /**
     * @test
     */
    public function it_can_convert_julian_to_gregorian_date()
    {
        $julianDate = '2024-03-20';
        $gregorianDate = CalendarHelper::JDtoISO8601($julianDate);
        
        // March 20, 2024 in Julian calendar is April 2, 2024 in Gregorian calendar
        $this->assertEquals('2024-04-02', $gregorianDate);
    }

    /**
     * @test
     */
    public function it_can_get_both_dates_for_gregorian_input()
    {
        $date = '2024-03-20';
        $result = CalendarHelper::getDates($date, false);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('julian', $result);
        $this->assertArrayHasKey('gregorian', $result);
        $this->assertEquals($date, $result['julian']);
        $this->assertEquals('2024-04-02', $result['gregorian']);
    }

    /**
     * @test
     */
    public function it_can_get_both_dates_for_julian_input()
    {
        $date = '2024-03-20';
        $result = CalendarHelper::getDates($date, true);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('julian', $result);
        $this->assertArrayHasKey('gregorian', $result);
        $this->assertEquals($date, $result['julian']);
        $this->assertEquals('2024-04-02', $result['gregorian']);
    }

    /**
     * @test
     */
    public function it_handles_negative_years_correctly()
    {
        $gregorianDate = '-0001-12-31';
        $julianDate = CalendarHelper::ISO8601toJD($gregorianDate);
        
        // The expected Julian Day number for December 31, 1 BCE
        $this->assertEquals(1721059, $julianDate);
    }
} 