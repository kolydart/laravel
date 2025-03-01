<?php

namespace Kolydart\Laravel\App\Rules;

use Illuminate\Contracts\Validation\Rule;
use DateTime;

/**
 * Validate that a date string is in ISO8601 format
 * @see https://en.m.wikipedia.org/wiki/ISO_8601
 * @see http://www.w3.org/TR/NOTE-datetime
 */
class ISO8601 implements Rule
{
    /** 
     * ISO8601, W3CDTF validation pattern
     * @see https://en.m.wikipedia.org/wiki/ISO_8601
     * @see http://www.w3.org/TR/NOTE-datetime
     */
    private const PREG_VALIDATE_ISO8601 = '/^(--|\/?-?\d\d\d\d)(-\d\d){0,2}(T\d\d(:\d\d){1,2}([-+]\d\d:\d\d|[A-Za-z]{3,4})?)?((\/(--|-?\d\d\d\d)(-\d\d){0,2}(T\d\d(:\d\d){1,2})?([-+]\d\d:\d\d|[A-Za-z]{3,4})?)|\/)?$/';

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_string($value)) {
            return false;
        }

        // Check format using the comprehensive ISO8601 regex pattern
        if (!preg_match(self::PREG_VALIDATE_ISO8601, $value)) {
            return false;
        }

        // For simple date formats (YYYY-MM-DD), also validate the date is real
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value;
        }

        // For other ISO8601 formats, the regex validation is sufficient
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('gw.iso8601');
    }
} 