# Changelog

All notable changes to `kolydart/laravel` are documented here.
This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) loosely; dates are ISO-8601.

## [Unreleased]

### Security

- **Stored XSS in `PgAuditLog` (all three variants).** PowerGrid renders field
  closure return values as raw HTML, but `user_name` and `properties_excerpt`
  passed database content through unescaped. Any user able to edit an audited
  text field — their own profile name suffices — could store markup that then
  executed in the browser of any staff member opening the audit log, in that
  user's session. All such values now go through `e()`. The V2 variant also
  built its excerpt with `rawurldecode(http_build_query(…))`, a pair that
  encodes and immediately decodes, handing back the raw payload; it now uses
  `json_encode` + `Str::limit` + `e()`.
- **`BackendAccess` middleware allowed guests through.** The guard read
  `method_exists(auth()->user(), 'has_backend_access') && !…`, whose left
  operand is false for a guest (`auth()->user()` is null) and for any user model
  that does not implement the method — so the request passed in exactly the
  cases that must be refused. It now denies unless the check exists and returns
  true. **Consumers relying on `backend` to be permissive for unauthenticated
  requests, or on user models without the `BackendAccessible` trait, will now
  receive 403.**
- **Impersonation TTL was never enforced.** `ttl_seconds` was documented as
  expiring impersonation sessions, but `EnforceImpersonationTimeout` had to be
  registered by hand and the documented procedure could not work: publishing it
  copies the file verbatim, so the published class still declares the package
  namespace and the `\App\Http\Middleware\…` entry the docs told you to add
  referred to a nonexistent class. The middleware is now appended to the `web`
  group automatically by `KolydartServiceProvider`; opt out with
  `kolydart.impersonate.auto_register_timeout = false`.
- **Impersonation route middleware fallback omitted `web`.** When
  `kolydart.impersonate.routes` was absent — which happens whenever an app
  published `config/kolydart.php` before that key existed, since
  `mergeConfigFrom()` merges only the top level — the impersonation POST routes
  were registered with `['auth']` alone, i.e. without `VerifyCsrfToken`. The
  fallback is now `['web', 'auth']`.
- **`PermissionsOverrideTableSeeder` could silently re-grant permissions.** It
  upserted on `id`, so an app that already used ids 1001-1003 for its own
  permissions had those rows *retitled* to `backend_access`, `datatables_csv`
  and `pulse_access` while their `permission_role` assignments stayed in place —
  granting every role that held the old permission the new one. It now matches
  on `title` and only claims a canonical id when that id is free.
- `Auditable` now drops `password` from the recorded changes instead of relying
  solely on the consumer model's `$hidden`.
- `MakeControllerTestCommand` and `MakeOrderedPivotMigration` now validate their
  name/table/column arguments. `Str::studly()` leaves `..` segments intact, so
  the former could write a test file outside the project; the latter
  interpolated its options into generated PHP unquoted, so a value containing a
  quote could inject code into the migration that runs next. Both are
  developer-supplied inputs at the same privilege level as the command, so this
  is hardening, not a privilege boundary fix.
- `<x-kolydart::ordered-select>` now filters attribute **names** against an
  identifier pattern. `e()` does not escape spaces or `=`, so a crafted
  attribute key could break out of the attribute position and inject a handler.

### Added

- `kolydart.audit_log.view_ability` — optional Gate ability checked in
  `PgAuditLog::datasource()`. `null` by default (no check), so existing
  installations are unaffected. Set it for defence in depth; the component
  otherwise relies entirely on the route that renders it, and without a `:model`
  parameter it exposes the whole `audit_logs` table.
- `kolydart.impersonate.auto_register_timeout` — opt out of automatic
  registration of `EnforceImpersonationTimeout`.
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

- `V3\PgAuditLog` called `route_exists()`, a helper the package never defines,
  producing a fatal error on any audit row whose subject is a `Media` record
  with a resolvable model. Now uses `Route::has()`, as the V6 variant does.
- `V2\PgAuditLog` imported `gateweb\common\Presenter`, an application class the
  package does not declare as a dependency, and called `route()` for the Media
  branch without a `Route::has()` guard. Both are removed.
- `EnforceImpersonationTimeout` no longer assumes a route named `login` exists;
  it falls back to `/`. Relevant now that it runs on the whole `web` group.
- `erd:generate` sets an explicit `0664` on the file it writes, so a run from a
  deploy script as root does not leave it unwritable by the web user.
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

Check these before upgrading — the security fixes above change runtime behaviour
in ways that can surface as new 403s or as differently seeded permissions.

1. **`backend` middleware now denies guests.** If any route group uses `backend`
   without `auth` in front of it, unauthenticated requests that previously
   passed will now get 403 instead of reaching the controller (and, in most
   apps, instead of being redirected to login). Put `auth` ahead of `backend`.
2. **User models without `has_backend_access()` are now denied.** The method
   comes from the `BackendAccessible` trait. If your `User` model does not use
   it, every `backend` route returns 403. Add the trait.
3. **The impersonation TTL now actually expires sessions.** Sessions older than
   `ttl_seconds` (default 3600) are logged out on their next request. If you had
   followed the old documentation and added
   `\App\Http\Middleware\EnforceImpersonationTimeout::class` to your kernel,
   remove it — that class never existed, and the package now registers its own.
4. **`PermissionsOverrideTableSeeder` no longer overwrites by id.** Re-running it
   will not repair rows that a previous run retitled. If ids 1001-1003 in your
   `permissions` table hold titles you did not intend, audit `permission_role`
   for roles that gained `backend_access`, `datatables_csv` or `pulse_access`
   through them.
5. **Audit `properties` no longer contain `password`.** Existing rows are
   untouched; consider purging historical entries that captured a password
   change on a model whose `$hidden` omitted the column.

If you previously relied on pivot-side `Auditable` to record relation
events, see the **Audited Relations → Migration guide** section in
[`README.md`](README.md#audited-relations). The short version: add
`HasAuditedRelations` to the parent, switch raw `sync()` / `attach()` /
`detach()` calls to their `audited*` equivalents, and remove
`use Auditable;` from the pivot models.
