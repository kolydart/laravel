<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trait HasOrderedPivot
 *
 * Provides functionality for models that need to maintain order in pivot relationships.
 * This trait allows you to define relationships where the order of related models is preserved.
 *
 * @package Kolydart\Laravel\App\Traits
 */
trait HasOrderedPivot
{
    /**
     * Define an ordered many-to-many relationship.
     *
     * @param string|null $related The related model class
     * @param string|null $table The pivot table name (optional)
     * @param string|null $foreignPivotKey The foreign key of the parent model (optional)
     * @param string|null $relatedPivotKey The foreign key of the related model (optional)
     * @param string|null $parentKey The local key of the parent model (optional)
     * @param string|null $relatedKey The local key of the related model (optional)
     * @param string $orderColumn The name of the order column in the pivot table (default: 'order')
     * @return BelongsToMany
     */
    public function orderedBelongsToMany(
        ?string $related = null,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        string $orderColumn = 'order'
    ): BelongsToMany {
        // Guard against calls without required parameters (e.g., from model:show command)
        if ($related === null) {
            // Return a dummy relationship for introspection purposes
            // This allows model:show and similar commands to work without errors
            return $this->belongsToMany(static::class, 'dummy_table')->withPivot($orderColumn);
        }

        $relationship = $this->belongsToMany(
            $related,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        )
        ->withPivot($orderColumn);

        // Get the actual pivot table name from the relationship
        $pivotTable = $relationship->getTable();

        return $relationship->orderBy($pivotTable . '.' . $orderColumn);
    }

    /**
     * Get the table name for the related model.
     *
     * @param string $related
     * @return string
     */
    protected function getRelatedTableName(string $related): string
    {
        return (new $related)->getTable();
    }

    /**
     * Sync related models with order preservation, using a smart diff so that
     * unchanged related records are not deleted/re-created. Order-only changes
     * update the pivot row silently (no pivot model events).
     *
     * @deprecated Use `HasAuditedRelations::auditedSyncWithOrder()` on the
     *             parent model for audited operations. This helper performs
     *             pivot writes without producing audit entries.
     *
     * @param BelongsToMany $relationship
     * @param array $ids Array of IDs in the desired order
     * @param string $orderColumn The name of the order column (default: 'order')
     * @return void
     * @throws \InvalidArgumentException
     */
    public function syncWithOrder(BelongsToMany $relationship, array $ids, string $orderColumn = 'order'): void
    {
        if (!$relationship instanceof BelongsToMany) {
            throw new \InvalidArgumentException('Relationship must be a BelongsToMany relationship.');
        }

        $ids = array_values(array_filter($ids, fn($id) => !empty($id)));

        $current = $relationship->withPivot($orderColumn)->get()
            ->mapWithKeys(fn($r) => [(int) $r->getKey() => (int) $r->pivot->{$orderColumn}]);
        $desired = collect($ids)->mapWithKeys(fn($id, $i) => [(int) $id => $i + 1]);

        foreach ($current->keys()->diff($desired->keys()) as $id) {
            $relationship->detach((int) $id);
        }

        foreach ($desired->keys()->diff($current->keys()) as $id) {
            $relationship->attach((int) $id, [$orderColumn => $desired[$id]]);
        }

        foreach ($desired->intersectByKeys($current) as $id => $newOrder) {
            if ($current[$id] !== $newOrder) {
                $relationship->newPivotQuery()
                    ->where($relationship->getRelatedPivotKeyName(), (int) $id)
                    ->update([$orderColumn => $newOrder]);
            }
        }
    }

    /**
     * Get the ordered IDs from a relationship.
     *
     * @param BelongsToMany $relationship
     * @param string $orderColumn The name of the order column (default: 'order')
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getOrderedIds(BelongsToMany $relationship, string $orderColumn = 'order'): array
    {
        if (!$relationship instanceof BelongsToMany) {
            throw new \InvalidArgumentException('Relationship must be a BelongsToMany relationship.');
        }

        return $relationship->orderBy($orderColumn)->pluck($relationship->getRelated()->getKeyName())->toArray();
    }
}
