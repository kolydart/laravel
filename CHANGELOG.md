# Changelog

All notable changes to `kolydart/laravel` are documented here.
This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) loosely; dates are ISO-8601.

## [Unreleased]

### Added

- `OrderedSelect.enableDragReorder()` — drag already-selected Select2 tags into a
  new order. Opt in per select with the `data-drag-reorder` attribute (added
  automatically by `<x-kolydart::ordered-select>` for multiple selects). Gated
  behind `typeof Sortable`, so it degrades silently when SortableJS is absent.
- SortableJS 1.15.6 bundled at `src/Resources/js/vendor/Sortable.min.js` and
  published to `public/vendor/kolydart/js/Sortable.min.js` under the existing
  `kolydart-ordered-pivot-js` tag — self-contained, no CDN dependency.
- `App\Support\OrderedPivotSync` — single implementation of the ordered-pivot
  smart diff (`diff()` computes it, `apply()` applies it).

- `HasAuditedRelations` trait — parent-side audited pivot operations
  (`auditedAttach`, `auditedDetach`, `auditedSync`, `auditedSyncWithoutDetaching`,
  `auditedToggle`).
- `HasAuditedRelations::auditedSyncWithOrder()` — smart-diff ordered sync;
  reorder-only changes produce zero audit entries.
- `HasAuditedRelations::auditedSyncRoledPivot()` — sync for pivot tables with
  a `(related_id, role)` identity. Role change emits detach + attach;
  same-role reorder is silent.
- `HasAuditedRelations::silentPivotUpdate()` — internal helper that bypasses
  pivot model events via `BelongsToMany::newPivotQuery()`. Needed because
  `getPivotClass()` is `protected` in Laravel 12.
- All audited mutating methods are wrapped in
  `$this->getConnection()->transaction(...)` for atomic pivot + audit writes.

- Laravel 12 support. `require-dev` now spans `^10.0|^11.0|^12.0` for the
  `illuminate/*` components and `laravel/framework`, and the suite is verified
  against Laravel 12 (`v12.x`) and Laravel 11.

### Changed

- `HasOrderedPivot::syncWithOrder()` / `getOrderedIds()`,
  `HandlesOrderedPivot::syncWithOrder()` / `getOrderedIds()` and
  `HasAuditedRelations::auditedSyncWithOrder()` now compute their diff through
  `OrderedPivotSync` instead of carrying near-identical copies of it. Behaviour
  is unchanged.
- Test suite now targets PHPUnit `^10.0|^11.0` (was `^9.0`) and uses native
  `#[Test]` attributes instead of the deprecated `/** @test */` doc-comment
  metadata, eliminating PHPUnit deprecation notices on PHPUnit 11.
- `HandlesOrderedPivot::syncWithOrder()` and `HasOrderedPivot::syncWithOrder()`
  now use a smart-diff (attach/detach only changed records, silent reorder
  for unchanged records). The previous detach-all + reattach-all behaviour
  produced phantom events on pivot models with `Auditable`. The method
  signature is unchanged — this is a behaviour-only improvement and is
  backward-compatible for callers.

### Fixed

- `getOrderedIds()` (both traits) threw `ambiguous column name` whenever the
  pivot table carried its own `id` column — the norm for pivots created with
  `make:ordered-pivot-migration` — because the key and the order column were
  selected unqualified across the join. Both are now table-qualified.
- Drag-to-reorder no longer binds a second SortableJS instance when
  `OrderedSelect.init()` runs twice over the same select (explicit call plus
  `autoInit()`, or a Livewire/Turbo re-render), which fired `onEnd` twice per
  drop.

### Deprecated

- `HandlesOrderedPivot::syncWithOrder()` and `HasOrderedPivot::syncWithOrder()`
  as entry points for **audited** workflows only. They carry no `@deprecated`
  tag, because they remain the canonical path for unaudited models. New audited
  code should call `HasAuditedRelations::auditedSyncWithOrder()` on the parent
  model instead.

### Migration notes for consumers

If you previously relied on pivot-side `Auditable` to record relation
events, see the **Audited Relations → Migration guide** section in
[`README.md`](README.md#audited-relations). The short version: add
`HasAuditedRelations` to the parent, switch raw `sync()` / `attach()` /
`detach()` calls to their `audited*` equivalents, and remove
`use Auditable;` from the pivot models.
