<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trait HandlesOrderedPivot
 *
 * Provides controller methods for handling ordered pivot relationships.
 * This trait simplifies the process of syncing related models while preserving their order.
 *
 * @package Kolydart\Laravel\App\Traits
 */
trait HandlesOrderedPivot
{
    /**
     * Sync a relationship with order preservation.
     *
     * @param Model $model The parent model
     * @param string $relationshipName The name of the relationship method
     * @param array $ids Array of IDs in the desired order
     * @param string $orderColumn The name of the order column (default: 'order')
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function syncWithOrder(Model $model, string $relationshipName, array $ids, string $orderColumn = 'order'): void
    {
        if (!method_exists($model, $relationshipName)) {
            throw new \InvalidArgumentException("Relationship method '{$relationshipName}' does not exist on model " . get_class($model));
        }

        $relationship = $model->{$relationshipName}();

        if (!$relationship instanceof BelongsToMany) {
            throw new \InvalidArgumentException("Relationship '{$relationshipName}' must be a BelongsToMany relationship.");
        }

        // Filter out empty values
        $ids = array_filter($ids, function($id) {
            return !empty($id);
        });

        // First, detach all existing relationships
        $relationship->detach();

        // Then attach with order
        foreach ($ids as $order => $id) {
            $relationship->attach($id, [$orderColumn => $order + 1]); // +1 to start from 1 instead of 0
        }
    }

    /**
     * Get ordered IDs from a relationship for form display.
     *
     * @param Model $model The parent model
     * @param string $relationshipName The name of the relationship method
     * @param string $orderColumn The name of the order column (default: 'order')
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function getOrderedIds(Model $model, string $relationshipName, string $orderColumn = 'order'): array
    {
        if (!method_exists($model, $relationshipName)) {
            throw new \InvalidArgumentException("Relationship method '{$relationshipName}' does not exist on model " . get_class($model));
        }

        $relationship = $model->{$relationshipName}();

        if (!$relationship instanceof BelongsToMany) {
            throw new \InvalidArgumentException("Relationship '{$relationshipName}' must be a BelongsToMany relationship.");
        }

        return $relationship->orderBy($orderColumn)->pluck($relationship->getRelated()->getKeyName())->toArray();
    }

    /**
     * Prepare ordered relationship data for edit forms.
     *
     * This method returns the selected IDs in their saved order, which can be used
     * in Blade templates to display selected options in the correct order.
     *
     * @param Model $model The parent model
     * @param string $relationshipName The name of the relationship method
     * @param string $orderColumn The name of the order column (default: 'order')
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function prepareOrderedRelationshipForEdit(Model $model, string $relationshipName, string $orderColumn = 'order'): array
    {
        return $this->getOrderedIds($model, $relationshipName, $orderColumn);
    }
}
