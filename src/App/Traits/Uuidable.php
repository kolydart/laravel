<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Auto fill uuid column on model creation
 */
trait Uuidable
{
    public static function bootUuidable()
    {
        self::creating(function (Model $model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}