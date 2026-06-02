# Changelog

All notable changes to `kolydart/laravel` are documented here.
This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) loosely; dates are ISO-8601.

## [Unreleased]

### Added

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

### Changed

- `HandlesOrderedPivot::syncWithOrder()` and `HasOrderedPivot::syncWithOrder()`
  now use a smart-diff (attach/detach only changed records, silent reorder
  for unchanged records). The previous detach-all + reattach-all behaviour
  produced phantom events on pivot models with `Auditable`. The method
  signature is unchanged — this is a behaviour-only improvement and is
  backward-compatible for callers.

### Deprecated

- `HandlesOrderedPivot::syncWithOrder()` and `HasOrderedPivot::syncWithOrder()`
  as entry points for **audited** workflows. They remain available for
  unaudited use. New audited code should call
  `HasAuditedRelations::auditedSyncWithOrder()` on the parent model.

### Migration notes for consumers

If you previously relied on pivot-side `Auditable` to record relation
events, see the **Audited Relations → Migration guide** section in
[`README.md`](README.md#audited-relations). The short version: add
`HasAuditedRelations` to the parent, switch raw `sync()` / `attach()` /
`detach()` calls to their `audited*` equivalents, and remove
`use Auditable;` from the pivot models.
