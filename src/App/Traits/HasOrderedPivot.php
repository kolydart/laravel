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
     * Sync related models with order preservation.
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

        // First, detach all existing relationships
        $relationship->detach();

        // Then attach with order
        foreach ($ids as $order => $id) {
            if (!empty($id)) { // Skip empty IDs
                $relationship->attach($id, [$orderColumn => $order + 1]); // +1 to start from 1 instead of 0
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
