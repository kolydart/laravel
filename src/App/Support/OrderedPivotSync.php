<?php

namespace Kolydart\Laravel\App\Support;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Single implementation of the ordered-pivot smart diff.
 *
 * `HasOrderedPivot` (model trait), `HandlesOrderedPivot` (controller trait) and
 * `HasAuditedRelations::auditedSyncWithOrder()` all compute their diff through
 * `diff()`, so the algorithm — and its phantom-event-free guarantees — lives in
 * exactly one place. The first two also apply it via `apply()`; the audited path
 * applies the same diff itself so it can interleave audit writes.
 *
 * @package Kolydart\Laravel\App\Support
 */
class OrderedPivotSync
{
    /**
     * Compute the smart diff between the stored pivot rows and the desired order.
     *
     * Order values are 1-indexed and follow the position of each id in `$ids`.
     * Empty ids are discarded before the comparison.
     *
     * @param BelongsToMany $relationship
     * @param array<int, int|string> $ids Related ids in the desired order
     * @param string $orderColumn
     * @return array{detach: array<int, int>, attach: array<int, int>, reorder: array<int, int>}
     *         `detach` is a list of ids; `attach` and `reorder` map id => order value.
     */
    public static function diff(BelongsToMany $relationship, array $ids, string $orderColumn = 'order'): array
    {
        $ids = array_values(array_filter($ids, fn ($id) => !empty($id)));

        $current = $relationship->withPivot($orderColumn)->get()
            ->mapWithKeys(fn ($related) => [(int) $related->getKey() => (int) $related->pivot->{$orderColumn}]);
        $desired = collect($ids)->mapWithKeys(fn ($id, $index) => [(int) $id => $index + 1]);

        return [
            'detach' => $current->keys()->diff($desired->keys())
                ->map(fn ($id) => (int) $id)->values()->all(),
            'attach' => $desired->keys()->diff($current->keys())
                ->mapWithKeys(fn ($id) => [(int) $id => $desired[$id]])->all(),
            'reorder' => $desired->intersectByKeys($current)
                ->filter(fn ($newOrder, $id) => $current[$id] !== $newOrder)->all(),
        ];
    }

    /**
     * Apply an ordered sync to a relationship.
     *
     * Attaches ids absent from the pivot, detaches ids absent from `$ids`, and
     * updates the order column of surviving rows via a raw pivot query so that
     * order-only changes fire no pivot model events.
     *
     * @param BelongsToMany $relationship
     * @param array<int, int|string> $ids Related ids in the desired order
     * @param string $orderColumn
     * @return void
     */
    public static function apply(BelongsToMany $relationship, array $ids, string $orderColumn = 'order'): void
    {
        $diff = static::diff($relationship, $ids, $orderColumn);

        foreach ($diff['detach'] as $id) {
            $relationship->detach($id);
        }

        foreach ($diff['attach'] as $id => $order) {
            $relationship->attach($id, [$orderColumn => $order]);
        }

        foreach ($diff['reorder'] as $id => $order) {
            $relationship->newPivotQuery()
                ->where($relationship->getRelatedPivotKeyName(), $id)
                ->update([$orderColumn => $order]);
        }
    }

    /**
     * Get the related ids of a relationship in their stored order.
     *
     * Both columns are table-qualified: the pivot table commonly carries its own
     * `id`, and the order column may exist on the related table too, so bare
     * names make sqlite/MySQL reject the join as ambiguous.
     *
     * @param BelongsToMany $relationship
     * @param string $orderColumn
     * @return array<int, int|string>
     */
    public static function orderedIds(BelongsToMany $relationship, string $orderColumn = 'order'): array
    {
        $related = $relationship->getRelated();

        return $relationship
            ->orderBy($relationship->getTable() . '.' . $orderColumn)
            ->pluck($related->qualifyColumn($related->getKeyName()))
            ->toArray();
    }
}
