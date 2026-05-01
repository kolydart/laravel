<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Auto fill uuid column on model creation (UUIDv4 via Str::uuid()).
 *
 * @deprecated Prefer Laravel's native HasUuids trait for new models (Laravel 9+).
 *
 * Migration path for models with bigint primary key + uuid secondary column:
 *
 *   use Illuminate\Database\Eloquent\Concerns\HasUuids;
 *
 *   class MyModel extends Model {
 *       use HasUuids;
 *
 *       public function uniqueIds(): array {
 *           return ['uuid']; // not 'id' — keeps bigint PK
 *       }
 *   }
 *
 * HasUuids generates UUIDv7 by default in Laravel 12 (lexicographically sortable,
 * better B-tree index performance vs UUIDv4).
 * To keep UUIDv4: override newUniqueId() returning (string) Str::uuid().
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