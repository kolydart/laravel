<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kolydart\Laravel\App\Support\OrderedPivotSync;

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
     * Sync a relationship with order preservation, using a smart diff so that
     * unchanged related records are not deleted/re-created. Order-only changes
     * update the pivot row silently (no pivot model events).
     *
     * Phantom-event-free: a sync with identical input produces zero DB writes
     * on the pivot table, and reorder produces only `UPDATE` statements.
     *
     * This is the canonical helper for models that are **not** audited. When the
     * model uses `HasAuditedRelations`, prefer `auditedSyncWithOrder()` instead —
     * it runs the same diff but also writes audit entries.
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
        OrderedPivotSync::apply($this->resolveOrderedRelationship($model, $relationshipName), $ids, $orderColumn);
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
        return OrderedPivotSync::orderedIds($this->resolveOrderedRelationship($model, $relationshipName), $orderColumn);
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

    /**
     * Resolve a relationship method name to its BelongsToMany instance.
     *
     * @param Model $model
     * @param string $relationshipName
     * @return BelongsToMany
     * @throws \InvalidArgumentException
     */
    private function resolveOrderedRelationship(Model $model, string $relationshipName): BelongsToMany
    {
        if (!method_exists($model, $relationshipName)) {
            throw new \InvalidArgumentException("Relationship method '{$relationshipName}' does not exist on model " . get_class($model));
        }

        $relationship = $model->{$relationshipName}();

        if (!$relationship instanceof BelongsToMany) {
            throw new \InvalidArgumentException("Relationship '{$relationshipName}' must be a BelongsToMany relationship.");
        }

        return $relationship;
    }
}
