<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Auto fill `uuid` column on model creation (UUIDv4 via Str::uuid()).
 *
 * @deprecated DO NOT use this trait for new models. Use Laravel's native HasUuids instead.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PROJECT STRATEGY (mandatory — chatbot read this before suggesting changes):
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Every model has BOTH:
 *   1. `id`   — bigint, auto-increment, PRIMARY KEY (used internally, FKs, joins)
 *   2. `uuid` — string, UNIQUE, secondary identifier (used in frontend / public URLs /
 *               external references — anywhere exposing the bigint id is undesirable)
 *
 * The bigint `id` stays the primary key. The `uuid` NEVER replaces it.
 *
 * UUID format: UUIDv7 (Laravel 12 default via HasUuids).
 *   - Lexicographically sortable
 *   - Better B-tree index performance than UUIDv4
 *   - Do NOT switch back to UUIDv4 unless there is a specific reason
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CORRECT setup for a new model (use this — NOT the Uuidable trait):
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   use Illuminate\Database\Eloquent\Concerns\HasUuids;
 *
 *   class MyModel extends Model {
 *       use HasUuids;
 *
 *       // CRITICAL: return ['uuid'], NOT ['id'].
 *       // Returning 'id' would make HasUuids overwrite the bigint PK with a UUID.
 *       public function uniqueIds(): array {
 *           return ['uuid'];
 *       }
 *   }
 *
 * Migration must define both columns:
 *
 *   $table->id();                  // bigint PK
 *   $table->uuid('uuid')->unique(); // secondary identifier
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DO / DON'T for the chatbot:
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   DO    use HasUuids + uniqueIds() returning ['uuid'] on every new model.
 *   DO    keep bigint `id` as the primary key on every model.
 *   DO    expose `uuid` (not `id`) in frontend routes, API payloads, public URLs.
 *   DO    migrate legacy models off this trait to HasUuids when touching them.
 *
 *   DON'T add `use Uuidable;` to new models.
 *   DON'T return ['id'] from uniqueIds() — that destroys the bigint PK.
 *   DON'T drop the `uuid` column from existing models.
 *   DON'T drop the `id` column or change PK to uuid.
 *   DON'T switch to UUIDv4 (this trait's behavior) for new code.
 * ─────────────────────────────────────────────────────────────────────────────
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