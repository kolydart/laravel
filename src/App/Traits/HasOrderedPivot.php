<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kolydart\Laravel\App\Support\OrderedPivotSync;

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
     * This is the canonical helper for models that are **not** audited. When the
     * model uses `HasAuditedRelations`, prefer `auditedSyncWithOrder()` instead —
     * it runs the same diff but also writes audit entries.
     *
     * @param BelongsToMany $relationship
     * @param array $ids Array of IDs in the desired order
     * @param string $orderColumn The name of the order column (default: 'order')
     * @return void
     */
    public function syncWithOrder(BelongsToMany $relationship, array $ids, string $orderColumn = 'order'): void
    {
        OrderedPivotSync::apply($relationship, $ids, $orderColumn);
    }

    /**
     * Get the ordered IDs from a relationship.
     *
     * @param BelongsToMany $relationship
     * @param string $orderColumn The name of the order column (default: 'order')
     * @return array
     */
    public function getOrderedIds(BelongsToMany $relationship, string $orderColumn = 'order'): array
    {
        return OrderedPivotSync::orderedIds($relationship, $orderColumn);
    }
}
