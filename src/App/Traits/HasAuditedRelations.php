<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;

/**
 * Trait που εκθέτει audited variants των pivot operations
 * (attach / detach / sync / syncWithoutDetaching / toggle) και
 * γράφει ένα audit entry ανά affected related id, με payload
 * της μορφής:
 *
 *   {
 *     "action":        "attach" | "detach" | "update",
 *     "relation":      "agents",
 *     "role":          "creator" | null,
 *     "related_id":    42,
 *     "related_type":  "App\\Models\\Agent",
 *     "related_label": "John Doe"
 *   }
 *
 * Σκοπός: τα pivot operations παράγουν *parent-side* audit entries που
 * επιτρέπουν reverse-query (από την άλλη πλευρά της σχέσης) χωρίς
 * διπλασιασμό αποθήκευσης, και αναγνώσιμο display.
 *
 * Δεν αντικαθιστά το `Auditable` trait — εστιάζει αποκλειστικά σε
 * relation operations. Μπορεί να συνυπάρχει με αυτό.
 *
 * @see kolydart-laravel/README ή l_helmarc roadmap §AU5 για ολοκληρωμένο spec.
 *
 * @changelog
 * 2026-06-02 (AU5)
 * - Initial implementation.
 * 2026-06-02 (Φ1)
 * - ADD: auditedSyncWithOrder() — smart-diff ordered sync (no phantom events on reorder).
 * - ADD: auditedSyncRoledPivot() — smart-diff sync for (id,role) identity pivots.
 * - ADD: silentPivotUpdate() — raw DB update bypassing pivot model events.
 * 2026-06-02 (Φ1.5)
 * - CHANGE: wrap όλα τα audited mutating methods σε transaction
 *   (getConnection()->transaction(...)) για ατομικότητα pivot mutation + audit write.
 */
trait HasAuditedRelations
{
    /**
     * Audited variant του BelongsToMany::attach().
     *
     * @param  string  $relation  το όνομα της σχέσης (π.χ. 'agents')
     * @param  mixed   $id        related id, model, Collection, ή array
     * @param  array<string,mixed>  $attributes  pivot attributes (συμπεριλαμβάνει 'role' αν υπάρχει)
     */
    public function auditedAttach(string $relation, $id, array $attributes = [], bool $touch = true): void
    {
        $this->getConnection()->transaction(function () use ($relation, $id, $attributes, $touch) {
            $relationObj = $this->resolveAuditedRelation($relation);

            $normalized = $this->normalizeAuditedIds($id, $attributes);

            $relationObj->attach($normalized, [], $touch);

            foreach ($normalized as $relatedId => $pivotAttrs) {
                $this->writeRelationAudit('attach', $relation, $relationObj, (int) $relatedId, $pivotAttrs);
            }
        });
    }

    /**
     * Audited variant του BelongsToMany::detach().
     *
     * @param  mixed  $ids  null για ολικό detach, ή id/array/Collection
     */
    public function auditedDetach(string $relation, $ids = null, bool $touch = true): int
    {
        return $this->getConnection()->transaction(function () use ($relation, $ids, $touch) {
            $relationObj = $this->resolveAuditedRelation($relation);

            // Snapshot των επερχόμενων detached records ΠΡΙΝ τη διαγραφή ώστε να
            // πιάσουμε pivot.role και related_label.
            $snapshot = $this->snapshotRelatedForDetach($relationObj, $ids);

            $count = $relationObj->detach($this->normalizeIdsOnly($ids), $touch);

            foreach ($snapshot as $row) {
                $this->writeRelationAudit('detach', $relation, $relationObj, (int) $row['related_id'], $row['pivot']);
            }

            return $count;
        });
    }

    /**
     * Audited variant του BelongsToMany::sync().
     *
     * Παράγει audit entries για: attached (new), detached (removed),
     * και updated (pivot attribute changes — π.χ. role change).
     *
     * @param  mixed  $ids  array | Collection | EloquentCollection
     * @return array{attached: array, detached: array, updated: array}
     */
    public function auditedSync(string $relation, $ids, bool $detaching = true): array
    {
        return $this->getConnection()->transaction(function () use ($relation, $ids, $detaching) {
            $relationObj = $this->resolveAuditedRelation($relation);

            $normalized = $this->normalizeAuditedIds($ids, []);

            // Snapshot παλιό state πριν το sync ώστε να μπορούμε να γράψουμε
            // role-change audit entries (updated).
            $beforeRoles = $this->snapshotPivotRoles($relationObj);

            $result = $detaching
                ? $relationObj->sync($normalized)
                : $relationObj->syncWithoutDetaching($normalized);

            foreach ($result['attached'] ?? [] as $relatedId) {
                $pivotAttrs = $normalized[$relatedId] ?? [];
                $this->writeRelationAudit('attach', $relation, $relationObj, (int) $relatedId, $pivotAttrs);
            }
            foreach ($result['detached'] ?? [] as $relatedId) {
                // Στο detach δεν έχουμε pivot attrs στο $normalized — δοκίμασε
                // από το $beforeRoles snapshot.
                $pivotAttrs = isset($beforeRoles[$relatedId])
                    ? ['role' => $beforeRoles[$relatedId]]
                    : [];
                $this->writeRelationAudit('detach', $relation, $relationObj, (int) $relatedId, $pivotAttrs);
            }
            foreach ($result['updated'] ?? [] as $relatedId) {
                $pivotAttrs = $normalized[$relatedId] ?? [];
                $this->writeRelationAudit('update', $relation, $relationObj, (int) $relatedId, $pivotAttrs);
            }

            return $result;
        });
    }

    /**
     * Audited syncWithoutDetaching — wrapper που καλεί auditedSync με detaching=false.
     *
     * @return array{attached: array, detached: array, updated: array}
     */
    public function auditedSyncWithoutDetaching(string $relation, $ids): array
    {
        return $this->auditedSync($relation, $ids, false);
    }

    /**
     * Audited variant του BelongsToMany::toggle().
     *
     * @return array{attached: array, detached: array}
     */
    public function auditedToggle(string $relation, $ids, bool $touch = true): array
    {
        return $this->getConnection()->transaction(function () use ($relation, $ids, $touch) {
            $relationObj = $this->resolveAuditedRelation($relation);

            $normalized = $this->normalizeAuditedIds($ids, []);

            $result = $relationObj->toggle($normalized, $touch);

            foreach ($result['attached'] ?? [] as $relatedId) {
                $pivotAttrs = $normalized[$relatedId] ?? [];
                $this->writeRelationAudit('attach', $relation, $relationObj, (int) $relatedId, $pivotAttrs);
            }
            foreach ($result['detached'] ?? [] as $relatedId) {
                $this->writeRelationAudit('detach', $relation, $relationObj, (int) $relatedId, []);
            }

            return $result;
        });
    }

    /**
     * Audited ordered sync for BelongsToMany with an order column.
     *
     * Smart diff: attaches new ids, detaches removed ids, silently reorders
     * ids that remain. Reorder-only changes produce NO audit entries.
     *
     * @param  string  $relation      relation method name (e.g. 'instruments')
     * @param  array   $ids           ordered flat list of related IDs
     * @param  string  $orderColumn   pivot column for order
     * @return array{attached: array<int>, detached: array<int>, updated: array}
     */
    public function auditedSyncWithOrder(string $relation, array $ids, string $orderColumn = 'order'): array
    {
        return $this->getConnection()->transaction(function () use ($relation, $ids, $orderColumn) {
            $rel = $this->resolveAuditedRelation($relation);
            $ids = array_values(array_filter($ids, fn($id) => !empty($id)));

            // Current state: related_id => current order value
            $cur = $rel->withPivot($orderColumn)->get()
                ->mapWithKeys(fn($r) => [(int) $r->getKey() => (int) $r->pivot->{$orderColumn}]);

            // Desired state: related_id => desired order (1-based)
            $des = collect($ids)->mapWithKeys(fn($id, $i) => [(int) $id => $i + 1]);

            $detached = [];
            $attached = [];

            // Detach ids not present in desired
            foreach ($cur->keys()->diff($des->keys()) as $id) {
                $snap = $this->snapshotRelatedForDetach($rel, [(int) $id]);
                $rel->detach((int) $id);
                foreach ($snap as $row) {
                    $this->writeRelationAudit('detach', $relation, $rel, (int) $row['related_id'], $row['pivot']);
                }
                $detached[] = (int) $id;
            }

            // Attach ids not present in current
            foreach ($des->keys()->diff($cur->keys()) as $id) {
                $rel->attach((int) $id, [$orderColumn => $des[$id]]);
                $this->writeRelationAudit('attach', $relation, $rel, (int) $id, [$orderColumn => $des[$id]]);
                $attached[] = (int) $id;
            }

            // Silent reorder for ids that remain but whose order changed
            foreach ($des->intersectByKeys($cur) as $id => $newOrder) {
                if ($cur[$id] !== $newOrder) {
                    $this->silentPivotUpdate($rel, (int) $id, [$orderColumn => $newOrder]);
                }
            }

            return ['attached' => $attached, 'detached' => $detached, 'updated' => []];
        });
    }

    /**
     * Audited sync for pivot tables with role + order columns.
     *
     * Identity key = (related_id, role) — changing role counts as
     * detach-old + attach-new. Reordering a same (id,role) pair is silent.
     *
     * @param  string  $relation       relation method name (e.g. 'agents')
     * @param  array   $rows           array of ['id' => int, 'role' => string, ...] maps
     * @param  string  $roleAttribute  pivot column for role
     * @param  string  $defaultRole    role value when not supplied in a row
     * @return array{attached: array<int>, detached: array<int>, updated: array}
     */
    public function auditedSyncRoledPivot(
        string $relation,
        array  $rows,
        string $roleAttribute = 'role',
        string $defaultRole   = 'creator'
    ): array {
        return $this->getConnection()->transaction(function () use ($relation, $rows, $roleAttribute, $defaultRole) {
            $rel = $this->resolveAuditedRelation($relation);

            $extractRole = fn($v) => $v === null
                ? $defaultRole
                : ($v instanceof \BackedEnum ? (string) $v->value : (string) $v);

            // Current: "id:role" => current order
            $cur = $rel->withPivot($roleAttribute, 'order')->get()
                ->mapWithKeys(fn($r) => [
                    (int) $r->getKey() . ':' . $extractRole($r->pivot->{$roleAttribute}) => (int) $r->pivot->order,
                ]);

            // Desired: "id:role" => desired order (1-based)
            $des = collect($rows)
                ->filter(fn($r) => !empty($r['id']))
                ->values()
                ->mapWithKeys(fn($r, $i) => [
                    (int) $r['id'] . ':' . ($r[$roleAttribute] ?? $defaultRole) => $i + 1,
                ]);

            $attached = [];
            $detached = [];

            // Detach (id,role) pairs absent from desired
            foreach ($cur->diffKeys($des) as $key => $_) {
                [$id, $role] = explode(':', $key, 2);
                $rel->wherePivot($roleAttribute, $role)->detach((int) $id);
                $this->writeRelationAudit('detach', $relation, $rel, (int) $id, [$roleAttribute => $role]);
                $detached[] = (int) $id;
            }

            // Attach new (id,role) pairs
            foreach ($des->diffKeys($cur) as $key => $order) {
                [$id, $role] = explode(':', $key, 2);
                $rel->attach((int) $id, [$roleAttribute => $role, 'order' => $order]);
                $this->writeRelationAudit('attach', $relation, $rel, (int) $id, [$roleAttribute => $role]);
                $attached[] = (int) $id;
            }

            // Silent reorder for (id,role) pairs that remain but order changed
            foreach ($des->intersectByKeys($cur) as $key => $order) {
                if ($cur[$key] !== $order) {
                    [$id, $role] = explode(':', $key, 2);
                    $this->silentPivotUpdate($rel, (int) $id, ['order' => $order], [$roleAttribute => $role]);
                }
            }

            return ['attached' => $attached, 'detached' => $detached, 'updated' => []];
        });
    }

    // ── helpers ────────────────────────────────────────────────────────────

    /**
     * Update a pivot row directly via query builder, bypassing pivot model events.
     *
     * Uses `newPivotQuery()` (same as `snapshotRelatedForDetach`) instead of
     * `updateExistingPivot()`, because the latter fires `updated` events on
     * the pivot model when the relation uses `using(PivotModel)`, producing
     * phantom audits. `newPivotQuery()` returns a raw query builder scoped to
     * the current parent — no model events, no Eloquent casts (safe for `order`).
     *
     * @param  BelongsToMany  $rel         the relation instance
     * @param  int            $relatedId   related record primary key
     * @param  array          $attrs       columns/values to update
     * @param  array          $extraWhere  additional WHERE conditions (e.g. ['role' => 'creator'])
     * @return int  rows affected
     */
    protected function silentPivotUpdate(BelongsToMany $rel, int $relatedId, array $attrs, array $extraWhere = []): int
    {
        // newPivotQuery() already scopes to WHERE foreign_pivot_key = parent_id
        // and returns a raw query builder — no Eloquent model events fired.
        $q = $rel->newPivotQuery()
            ->where($rel->getRelatedPivotKeyName(), $relatedId);

        foreach ($extraWhere as $col => $v) {
            $q->where($col, $v);
        }

        return $q->update($attrs);
    }

    /**
     * Resolve το relation object. Πετάει LogicException αν δεν είναι BelongsToMany/MorphToMany.
     */
    protected function resolveAuditedRelation(string $relation): BelongsToMany
    {
        if (!method_exists($this, $relation)) {
            throw new \LogicException(
                sprintf('%s::%s() δεν υπάρχει. HasAuditedRelations απαιτεί ορισμένη σχέση.', static::class, $relation)
            );
        }

        $relationObj = $this->{$relation}();

        if (!$relationObj instanceof BelongsToMany) {
            throw new \LogicException(
                sprintf('%s::%s() πρέπει να είναι BelongsToMany ή MorphToMany.', static::class, $relation)
            );
        }

        return $relationObj;
    }

    /**
     * Κανονικοποίηση του $ids ορίσματος σε format `[id => pivotAttrs]`.
     *
     * Δέχεται: int, string, Model, EloquentCollection, array (numeric ή assoc),
     *          Illuminate\Support\Collection.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function normalizeAuditedIds($ids, array $defaultAttrs): array
    {
        if ($ids instanceof Model) {
            return [(int) $ids->getKey() => $defaultAttrs];
        }

        if ($ids instanceof EloquentCollection) {
            $out = [];
            foreach ($ids as $model) {
                $out[(int) $model->getKey()] = $defaultAttrs;
            }
            return $out;
        }

        if ($ids instanceof \Illuminate\Support\Collection) {
            return $this->normalizeAuditedIds($ids->all(), $defaultAttrs);
        }

        if (is_array($ids)) {
            $out = [];
            foreach ($ids as $key => $value) {
                if (is_array($value)) {
                    $out[(int) $key] = array_merge($defaultAttrs, $value);
                } elseif ($value instanceof Model) {
                    $out[(int) $value->getKey()] = $defaultAttrs;
                } else {
                    $out[(int) $value] = $defaultAttrs;
                }
            }
            return $out;
        }

        if (is_numeric($ids)) {
            return [(int) $ids => $defaultAttrs];
        }

        return [];
    }

    /**
     * Όπως το normalizeAuditedIds() αλλά επιστρέφει μόνο τα ids
     * (για να περάσει σε underlying detach()).
     *
     * @return array<int>|null  null αν το $ids είναι null (ολικό detach)
     */
    protected function normalizeIdsOnly($ids): ?array
    {
        if ($ids === null) {
            return null;
        }
        return array_keys($this->normalizeAuditedIds($ids, []));
    }

    /**
     * Snapshot πριν από detach: επιστρέφει [['related_id' => int, 'pivot' => ['role' => ...]], ...]
     */
    protected function snapshotRelatedForDetach(BelongsToMany $relation, $ids): array
    {
        $relatedKey = $relation->getRelatedKeyName();
        $query = $relation->newPivotQuery();

        if ($ids !== null) {
            $idArray = array_keys($this->normalizeAuditedIds($ids, []));
            $query->whereIn($relation->getRelatedPivotKeyName(), $idArray);
        }

        $rows = $query->get();
        $out  = [];

        foreach ($rows as $row) {
            $relatedId = $row->{$relation->getRelatedPivotKeyName()} ?? null;
            if ($relatedId === null) {
                continue;
            }
            $pivot = [];
            if (isset($row->role)) {
                $pivot['role'] = $row->role;
            }
            $out[] = [
                'related_id' => $relatedId,
                'pivot'      => $pivot,
            ];
        }

        return $out;
    }

    /**
     * Snapshot pivot.role values πριν από sync (για να γράψουμε σωστά τα detached).
     *
     * @return array<int,string|null>  related_id => role
     */
    protected function snapshotPivotRoles(BelongsToMany $relation): array
    {
        $relatedPivotKey = $relation->getRelatedPivotKeyName();
        $rows = $relation->newPivotQuery()->get();
        $out  = [];

        foreach ($rows as $row) {
            $relatedId = $row->{$relatedPivotKey} ?? null;
            if ($relatedId === null) {
                continue;
            }
            $out[(int) $relatedId] = $row->role ?? null;
        }

        return $out;
    }

    /**
     * Γράφει ένα audit entry στο AuditLog για ένα relation event.
     */
    protected function writeRelationAudit(string $action, string $relation, BelongsToMany $relationObj, int $relatedId, array $pivotAttrs): void
    {
        $relatedType  = get_class($relationObj->getRelated());
        $role         = $this->extractRoleFromPivot($pivotAttrs);
        $relatedLabel = $this->resolveAuditedRelatedLabel($relatedType, $relatedId);

        $properties = [
            'action'        => $action,
            'relation'      => $relation,
            'role'          => $role,
            'related_id'    => $relatedId,
            'related_type'  => $relatedType,
            'related_label' => $relatedLabel,
        ];

        $auditLogClass = $this->getAuditLogModelForRelations();
        $userId        = auth()->id() ?? null;
        $host          = $this->getAuditLogHostForRelations();

        $description = 'relation_' . $action;  // relation_attach | relation_detach | relation_update

        try {
            $auditLogClass::create([
                'description'  => $description,
                'subject_id'   => $this->getKey(),
                'subject_type' => static::class,
                'user_id'      => $userId,
                'properties'   => $properties,
                'host'         => $host,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('HasAuditedRelations audit write failed', [
                'exception'    => $e->getMessage(),
                'subject_type' => static::class,
                'subject_id'   => $this->getKey(),
                'description'  => $description,
                'properties'   => $properties,
            ]);
        }
    }

    /**
     * Επιστρέφει την τιμή του pivot.role αν υπάρχει στο $attributes.
     */
    protected function extractRoleFromPivot(array $attributes): ?string
    {
        if (array_key_exists('role', $attributes)) {
            return $attributes['role'] !== null ? (string) $attributes['role'] : null;
        }
        return null;
    }

    /**
     * Snapshot label του related record. Override-able από το host model.
     *
     * Default σειρά: full_name → title → name → class_basename + '#' + id.
     */
    protected function resolveAuditedRelatedLabel(string $relatedType, int $relatedId): string
    {
        if (!class_exists($relatedType)) {
            return class_basename($relatedType) . '#' . $relatedId;
        }

        /** @var Model|null $record */
        $record = $relatedType::query()->find($relatedId);
        if (!$record) {
            return class_basename($relatedType) . '#' . $relatedId;
        }

        foreach (['full_name', 'title', 'name'] as $attr) {
            $value = $record->{$attr} ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($relatedType) . '#' . $relatedId;
    }

    /**
     * Resolve AuditLog model FQCN (mirror of Auditable::getAuditLogModel()).
     */
    protected function getAuditLogModelForRelations(): string
    {
        return class_exists('App\Models\AuditLog') ? 'App\Models\AuditLog' : 'App\AuditLog';
    }

    /**
     * Resolve client IP για audit (mirror of Auditable::getAuditLogIp()).
     */
    protected function getAuditLogHostForRelations(): ?string
    {
        if (!config('audit-log.store_ip', true)) {
            return null;
        }
        try {
            return request()->ip() ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
