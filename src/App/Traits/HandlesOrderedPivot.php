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
     * Sync a relationship with order preservation, using a smart diff so that
     * unchanged related records are not deleted/re-created. Order-only changes
     * update the pivot row silently (no pivot model events).
     *
     * Phantom-event-free: a sync with identical input produces zero DB writes
     * on the pivot table, and reorder produces only `UPDATE` statements.
     *
     * @deprecated Use `HasAuditedRelations::auditedSyncWithOrder()` on the
     *             parent model for audited operations. This helper performs
     *             pivot writes without producing audit entries.
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
