# kolydart/laravel

> **Document Purpose**: Technical documentation for Laravel package providing ordered pivot relationships management tools. Describes installation, usage, and API reference.

A collection of Laravel helper classes including ordered pivot relationships functionality.

> Additional documentation is available in the [`src/docs/`](src/docs/) folder.

> ⚠️ **This repository is public.** Everything committed here — code, documentation, tests, commit messages — is world readable and permanently recorded in the git history, where a later commit cannot retract it. Contributions must therefore contain **no** hostnames or server names, internal domains or IP addresses, filesystem paths from real deployments, database or application names, client or project names, e-mail addresses, credentials, or references to private repositories and internal documents. Use neutral placeholders instead: `example.com`, `myapp`, `<host>`, `/path/to/app`. Anything meaningful to only one organisation belongs in that organisation's private repository; what is documented here is the general mechanism.

## Table of Contents

- [Installation](#installation)
- [Security](#security)
- [Audited Relations](#audited-relations)
- [Ordered Pivot Relationships](#ordered-pivot-relationships)
  - [Quick Start](#quick-start)
  - [Components](#components)
    - [Drag-to-reorder](#drag-to-reorder)
  - [Usage Examples](#usage-examples)
  - [API Reference](#api-reference)
  - [Migration from Manual Implementation](#migration-from-manual-implementation)
- [Private Media](#private-media)
- [Additional Components](#additional-components)
- [Testing Helpers](#testing-helpers)
  - [`InteractsWithDatatables`](#interactswithdatatables)
  - [`InteractsWithSmokeCrawler`](#interactswithsmokecrawler)
- [Testing](#testing)
- [License](#license)

## Installation

```bash
composer require kolydart/laravel
```

The service provider will be automatically registered via Laravel's package auto-discovery.

## Security

Notes for consumer applications. See the [changelog](CHANGELOG.md) for the fixes behind them.

### The audit log grid renders escaped values — keep it that way

PowerGrid renders field closure return values as **raw HTML** (that is what lets `PgAuditLog` return `<a href="…">` links). Audit `properties` and user names are attacker-controlled: a user who can edit any audited text field — their own profile name is enough — can store markup that executes in the browser of whoever opens the audit log.

Every such value in `PgAuditLog` is therefore passed through `e()`. If you subclass the component or add your own fields, escape anything that originates from the database:

```php
->add('my_field', fn ($model) => e($model->some_user_supplied_value))
```

### Restricting who can read the audit log

`PgAuditLog` performs no authorization of its own. Rendered without a `:model` parameter it exposes the whole `audit_logs` table — who did what, to which record, from which IP. Authorization is normally handled by the route that renders it. For defence in depth, name a Gate ability:

```php
// config/kolydart.php
'audit_log' => [
    'view_ability' => 'audit_log_access',
],
```

The check runs in `datasource()`. It is `null` (no check) by default, so existing installations are unaffected until they opt in — set it only once the ability actually exists, or every audit grid will 403.

### `Auditable` and sensitive columns

`Auditable` writes changed attributes into `audit_logs.properties`, which is readable by every role holding audit-log access. It drops `password`, `remember_token`, `two_factor_code` and `two_factor_expires_at` explicitly; everything else is filtered only by your model's `$hidden`.

If a model carries secrets under other names — API tokens, recovery codes, national identifiers — add them to `$hidden` on that model.

### `backend` middleware denies by default

`BackendAccess` aborts with 403 for guests and for user models that do not implement `has_backend_access()`. Before this was the case it allowed both through, so a route group protected by `backend` alone was open. It is still correct to place `auth` ahead of it; the middleware no longer depends on you doing so.

### Publishing `config/kolydart.php`

`mergeConfigFrom()` merges only the top level. A published config containing an `impersonate` key replaces that whole sub-array, so any key you omit goes missing rather than falling back to the package default. Diff your published copy against `src/config/kolydart.php` after upgrading.

## Audited Relations

The `HasAuditedRelations` trait provides audited variants of `BelongsToMany` pivot operations. Audit entries are written on the **parent** model — never on pivot models — which avoids double-audits and lets you reverse-query from either side of the relation.

### When to use

Use `HasAuditedRelations` on a parent model when you need to:

- audit `attach` / `detach` / `sync` / `toggle` operations on a relation
- record additional pivot attributes (e.g. `role`)
- reorder a `BelongsToMany` relation **silently** (no phantom audit entries)
- avoid the double-audit problem caused by pivot models using `Auditable`

```php
use Kolydart\Laravel\App\Traits\HasAuditedRelations;

class Item extends Model
{
    use HasAuditedRelations;

    public function agents() { return $this->belongsToMany(Agent::class)->withPivot('role', 'order'); }
    public function instruments() { return $this->belongsToMany(Instrument::class)->withPivot('order'); }
}

// In a controller:
$item->auditedSyncRoledPivot('agents', $request->input('agents', []));
$item->auditedSyncWithOrder('instruments', $request->input('instruments', []));
$item->auditedSync('languages', $request->input('languages', []));
$item->auditedAttach('places', $place);
$item->auditedDetach('places', $place);
```

### API

| Method | Use case |
|--------|----------|
| `auditedAttach(string $relation, $id, array $attrs = [], bool $touch = true)` | Single (or batch) attach with audit |
| `auditedDetach(string $relation, $ids = null, bool $touch = true): int` | Detach with audit. `null` detaches all. |
| `auditedSync(string $relation, $ids, bool $detaching = true): array` | Full sync, equivalent to `sync()` |
| `auditedSyncWithoutDetaching(string $relation, $ids): array` | Wrapper around `auditedSync(..., false)` |
| `auditedToggle(string $relation, $ids, bool $touch = true): array` | Toggle with audit |
| `auditedSyncWithOrder(string $relation, array $ids, string $orderColumn = 'order'): array` | Ordered sync — smart-diff, silent reorder |
| `auditedSyncRoledPivot(string $relation, array $rows, string $roleAttribute = 'role', string $defaultRole = 'creator'): array` | Sync where identity is `(related_id, role)`. Role change = detach + attach. |

Each method is wrapped in a transaction so that pivot mutation and audit write succeed or fail atomically.

#### Audit payload

```json
{
  "action":        "attach" | "detach" | "update",
  "relation":      "agents",
  "role":          "creator",
  "related_id":    42,
  "related_type":  "App\\Models\\Agent",
  "related_label": "John Doe"
}
```

The audit `description` is `relation_attach`, `relation_detach`, or `relation_update`, and `subject_type` / `subject_id` always point to the parent model.

### Important: do not use `Auditable` on pivot models

The recommended strategy is **parent-side audits only**. Pivot models (those extending `Illuminate\Database\Eloquent\Relations\Pivot`) should **not** use `Auditable`. Otherwise, a single `attach()`/`detach()` produces two audit entries: one parent-side (from `HasAuditedRelations`) and one pivot-side (from the pivot's `Auditable`).

### Migration guide for existing installations

If you currently rely on pivot-side `Auditable`, migrate as follows:

1. Locate pivot models using `Auditable` whose parent has, or will have, `HasAuditedRelations`:
   ```bash
   grep -rln "use App\\\\Traits\\\\Auditable" app/Models/ | xargs grep -l "extends Pivot"
   ```
2. Locate raw `sync()` / `attach()` / `detach()` / legacy `syncWithOrder` / `syncRoledPivot` calls in controllers that operate on audited relations:
   ```bash
   grep -rn "->sync(\|->attach(\|->detach(\|syncWithOrder\|syncRoledPivot" app/Http/Controllers/ --include="*.php"
   ```
3. Replace those calls with the `audited*` equivalents (`auditedSync`, `auditedSyncWithOrder`, `auditedSyncRoledPivot`).
4. Remove `use Auditable;` from the matching pivot models — both the trait use and the `App\Traits\Auditable` import.
5. Add `HasAuditedRelations` to any parent model not yet using it.

After migration, audited operations emit a single parent-side audit entry per affected related record.

## Ordered Pivot Relationships

This package provides functionality to maintain order in many-to-many (pivot) relationships. This abstraction allows you to preserve the selection order of related models, which is particularly useful for forms where the order of selection matters.

> **Which sync helper?** The `syncWithOrder()` helpers in `HasOrderedPivot` and `HandlesOrderedPivot` are smart-diff and phantom-event-free, and are the canonical path for **unaudited** models. They do **not** produce audit entries — when the parent model uses `HasAuditedRelations`, use [`auditedSyncWithOrder()`](#audited-relations) instead. All three compute their diff through the same implementation (`App\Support\OrderedPivotSync::diff()`), so ordering semantics are identical: 1-indexed, following the submitted array order. Only the application differs — the audited path pairs each attach/detach with an audit entry and wraps the whole operation in a transaction.

### Quick Start

#### 1. Create migration for order column:
```bash
php artisan make:ordered-pivot-migration paper_user --order-column=order --after=user_id
```

This creates a migration that adds an `order` column to the `paper_user` pivot table.

#### 2. Update your model:
```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Kolydart\Laravel\App\Traits\HasOrderedPivot;

class Paper extends Model
{
    use HasOrderedPivot;

    public function users()
    {
        return $this->orderedBelongsToMany(User::class)
                    ->withPivot('order')
                    ->orderBy('paper_user.order');
    }
}
```

#### 3. Update your controller:
```php
<?php

namespace App\Http\Controllers;

use Kolydart\Laravel\App\Traits\HandlesOrderedPivot;

class PaperController extends Controller
{
    use HandlesOrderedPivot;

    public function store(Request $request)
    {
        $paper = Paper::create($request->validated());
        $this->syncWithOrder($paper, 'users', $request->input('users', []));
        return redirect()->route('papers.index');
    }

    public function edit(Paper $paper)
    {
        $users = User::pluck('name', 'id');
        $selectedUsers = $this->getOrderedIds($paper, 'users');
        return view('papers.edit', compact('paper', 'users', 'selectedUsers'));
    }
}
```

#### 4. Add assets to your build process:

##### For Vite (Laravel 9+):

Add to your `vite.config.js`:
```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'public/vendor/kolydart/js/ordered-select.js'  // Add this line
            ],
            refresh: true,
        }),
    ],
});
```

Then in your Blade layout:
```blade
@vite(['resources/css/app.css', 'resources/js/app.js', 'public/vendor/kolydart/js/ordered-select.js'])
```

##### For Laravel Mix:

Add to your `webpack.mix.js`:
```javascript
mix.js('resources/js/app.js', 'public/js')
   .postCss('resources/css/app.css', 'public/css')
   .copy('public/vendor/kolydart/js/ordered-select.js', 'public/js/ordered-select.js');
```

Then in your Blade layout:
```blade
<script src="{{ asset('js/ordered-select.js') }}"></script>
```

##### Manual inclusion (current setup):

Publish the assets, then load them **after** Select2 and after whatever code calls `.select2()`:

```bash
php artisan vendor:publish --tag=kolydart-ordered-pivot-js
```

```blade
<script src="{{ asset('vendor/kolydart/js/Sortable.min.js') }}"></script>
<script src="{{ asset('vendor/kolydart/js/ordered-select.js') }}"></script>
```

`Sortable.min.js` (SortableJS 1.15.6) is bundled with the package and is only needed for [drag-to-reorder](#drag-to-reorder). Omit it and everything else keeps working.

#### 5. Use in your Blade templates:

```blade
{{-- Using the Blade component --}}
<x-kolydart::ordered-select
    name="users"
    :options="$users"
    :selected="$selectedUsers ?? []"
    multiple
    class="form-control select2"
/>

{{-- Or manually with the ordered-select class --}}
<select name="users[]" class="form-control select2 ordered-select" multiple>
    @foreach($users as $id => $name)
        <option value="{{ $id }}">{{ $name }}</option>
    @endforeach
</select>

{{-- Using Livewire component for dynamic options --}}
@livewire('kolydart-ordered-select', [
    'name' => 'users',
    'options' => $users,
    'selected' => $selectedUsers ?? [],
    'multiple' => true,
    'allowAdd' => true,
    'modelClass' => 'App\\User',
    'displayField' => 'name',
    'valueField' => 'id'
])
```

### Components

#### 1. Model Trait: `HasOrderedPivot`

Provides methods for models that need ordered relationships.

**Methods:**
- `orderedBelongsToMany()` - Define an ordered many-to-many relationship
- `syncWithOrder()` - Sync related models with order preservation
- `getOrderedIds()` - Get ordered IDs from a relationship

#### 2. Controller Trait: `HandlesOrderedPivot`

Provides controller methods for handling ordered pivot relationships.

**Methods:**
- `syncWithOrder()` - Sync a relationship with order preservation
- `getOrderedIds()` - Get ordered IDs for form display
- `prepareOrderedRelationshipForEdit()` - Prepare data for edit forms

#### 3. Artisan Command: `make:ordered-pivot-migration`

Generates migrations for adding order columns to pivot tables.

**Usage:**
```bash
php artisan make:ordered-pivot-migration {table} [--order-column=order] [--after=column]
```

#### 4. JavaScript Component: `OrderedSelect`

Preserves selection order in Select2 dropdowns and provides dynamic option management.

**Methods:**
- `OrderedSelect.init()` - Auto-initialize all elements with the 'ordered-select' class or the `data-drag-reorder` attribute
- `OrderedSelect.enableDragReorder($select)` - Make the selected tags draggable (see [Drag-to-reorder](#drag-to-reorder))
- `OrderedSelect.getOrderedValues($select)` - Get selected values in order
- `OrderedSelect.setOrderedValues($select, values)` - Set values in specific order
- `OrderedSelect.addOption($select, value, text, selected, preserveOrder)` - Add new option
- `OrderedSelect.createAddForm($select, config)` - Create modal for adding options

##### Drag-to-reorder

`OrderedSelect` keeps the order in which items were **selected**. On top of that, already-selected tags can be **dragged** into a new order. Opt in per select with the `data-drag-reorder` attribute:

```blade
<select name="pages[]" id="pages" class="form-control select2" multiple data-drag-reorder>
    @foreach($pages as $id => $page)
        <option value="{{ $id }}" @selected(in_array($id, $selectedPages))>{{ $page }}</option>
    @endforeach
</select>
```

The `<x-kolydart::ordered-select>` component adds the attribute automatically for multiple selects; pass `:drag-reorder="false"` to switch it off.

Notes:

- Requires SortableJS. Load the bundled `vendor/kolydart/js/Sortable.min.js` before `ordered-select.js`. **If SortableJS is missing the feature is simply skipped** — selection-order behaviour is unaffected.
- On drop, the tag order is mirrored onto the underlying `<option>` elements, so the submitted `name[]` order matches what the user sees, and `syncWithOrder()` / `auditedSyncWithOrder()` persist exactly that.
- The Select2 search field stays pinned at the end of the tag list.
- `cursor: move` on the tags is injected by the script; no stylesheet to publish.
- The select must already be initialised by Select2 — `ordered-select.js` retries for ~1s to accommodate late initialisation.

#### 5. Blade Component: `<x-kolydart::ordered-select>`

Reusable component for ordered select fields.

#### 6. Livewire Component: `@livewire('kolydart-ordered-select')`

Advanced component with dynamic option addition and real-time updates.

#### 7. Blade Component: `<x-kolydart::table-hide-empty-rows>`

Hides table rows that contain empty cells. Useful for cleaning up tables with sparse data.

**Properties:**
- `excludedTables`: CSS selector for tables to exclude (default: `.power-grid-table`)
- `excludedContainers`: CSS selector for containers to exclude (default: `div.tab-pane`)

**Usage:**
```blade
<x-kolydart::table-hide-empty-rows />

{{-- With custom exclusions --}}
<x-kolydart::table-hide-empty-rows
    excludedTables=".my-custom-table"
    excludedContainers=".modal-body"
/>
```

### Usage Examples

#### Example 1: Basic Paper-User Relationship

```php
// Model
class Paper extends Model
{
    use HasOrderedPivot;

    public function users()
    {
        return $this->orderedBelongsToMany(User::class);
    }
}

// Controller
class PaperController extends Controller
{
    use HandlesOrderedPivot;

    public function store(StorePaperRequest $request)
    {
        $paper = Paper::create($request->all());
        $this->syncWithOrder($paper, 'users', $request->input('users', []));
        return redirect()->route('papers.index');
    }

    public function edit(Paper $paper)
    {
        $users = User::pluck('name', 'id');
        $selectedUsers = $this->getOrderedIds($paper, 'users');
        return view('papers.edit', compact('paper', 'users', 'selectedUsers'));
    }
}
```

#### Example 2: Custom Order Column

```php
// Migration
php artisan make:ordered-pivot-migration project_task --order-column=priority --after=task_id

// Model
class Project extends Model
{
    use HasOrderedPivot;

    public function tasks()
    {
        return $this->orderedBelongsToMany(Task::class, null, null, null, null, null, 'priority');
    }
}

// Controller
$this->syncWithOrder($project, 'tasks', $taskIds, 'priority');
```

#### Example 3: Dynamic Option Addition

```javascript
// Add "Add New User" functionality
function addNewUser() {
    const $select = $('#users');
    OrderedSelect.createAddForm($select, {
        title: 'Add New User',
        label: 'User Name',
        onSubmit: function(name, callback) {
            // Make AJAX call to create user
            $.post('/api/users', {name: name}, function(response) {
                callback(response.id, response.name);
            });
        }
    });
}
```

#### Example 4: Using JavaScript Directly

```javascript
// Initialize ordered select with custom options
OrderedSelect.init();

// Get current order
const orderedValues = OrderedSelect.getOrderedValues($('#my-select'));

// Set specific order
OrderedSelect.setOrderedValues($('#my-select'), [3, 1, 4, 2]);

// Add new option dynamically
OrderedSelect.addOption($('#my-select'), 'new-id', 'New Option', true, true);
```

### API Reference

#### HasOrderedPivot Trait

##### `orderedBelongsToMany()`

```php
public function orderedBelongsToMany(
    string $related,
    string $table = null,
    string $foreignPivotKey = null,
    string $relatedPivotKey = null,
    string $parentKey = null,
    string $relatedKey = null,
    string $orderColumn = 'order'
): BelongsToMany
```

##### `syncWithOrder()`

```php
public function syncWithOrder(
    BelongsToMany $relationship,
    array $ids,
    string $orderColumn = 'order'
): void
```

##### `getOrderedIds()`

```php
public function getOrderedIds(
    BelongsToMany $relationship,
    string $orderColumn = 'order'
): array
```

#### HandlesOrderedPivot Trait

##### `syncWithOrder()`

```php
protected function syncWithOrder(
    Model $model,
    string $relationshipName,
    array $ids,
    string $orderColumn = 'order'
): void
```

##### `getOrderedIds()`

```php
protected function getOrderedIds(
    Model $model,
    string $relationshipName,
    string $orderColumn = 'order'
): array
```

##### `prepareOrderedRelationshipForEdit()`

```php
protected function prepareOrderedRelationshipForEdit(
    Model $model,
    string $relationshipName,
    string $orderColumn = 'order'
): array
```

#### OrderedSelect JavaScript

##### `init()`

```javascript
OrderedSelect.init() // Auto-initializes all .ordered-select elements
```

##### `getOrderedValues()`

```javascript
OrderedSelect.getOrderedValues($select) // Returns: Array
```

##### `setOrderedValues()`

```javascript
OrderedSelect.setOrderedValues($select, values) // Returns: void
```

##### `addOption()`

```javascript
OrderedSelect.addOption($select, value, text, selected = false, preserveOrder = true)
```

##### `createAddForm()`

```javascript
OrderedSelect.createAddForm($select, config = {
    title: 'Add New Option',
    label: 'Name',
    onSubmit: function(text, callback) { /* custom logic */ }
})
```

#### Blade Component

```blade
<x-kolydart::ordered-select
    name="field_name"
    :options="$options"
    :selected="$selected"
    :multiple="true"
    placeholder="Select options..."
    :required="false"
    class="additional-classes"
    :attributes="['data-custom' => 'value']"
/>
```

#### Livewire Component

```blade
@livewire('kolydart-ordered-select', [
    'name' => 'field_name',
    'options' => $options,
    'selected' => $selected,
    'multiple' => true,
    'allowAdd' => true,
    'modelClass' => 'App\\Model',
    'displayField' => 'name',
    'valueField' => 'id'
])
```

### Migration from Manual Implementation

If you have an existing manual implementation, here's how to migrate:

#### 1. Replace Manual Traits

**Before:**
```php
// Custom syncUsersWithOrder method in controller
private function syncUsersWithOrder(Paper $paper, array $userIds)
{
    $paper->users()->detach();
    foreach ($userIds as $order => $userId) {
        $paper->users()->attach($userId, ['order' => $order + 1]);
    }
}
```

**After:**
```php
use HandlesOrderedPivot;

// Use the trait method
$this->syncWithOrder($paper, 'users', $userIds);
```

#### 2. Update Model Relationships

**Before:**
```php
public function users()
{
    return $this->belongsToMany(User::class)->withPivot('order')->orderBy('paper_user.order');
}
```

**After:**
```php
use HasOrderedPivot;

public function users()
{
    return $this->orderedBelongsToMany(User::class);
}
```

#### 3. Simplify JavaScript

**Before:**
```javascript
$('#users').on('select2:select', function (e) {
    var element = e.params.data.element;
    var $element = $(element);
    $element.detach();
    $(this).append($element);
    $(this).trigger('change');
});
```

**After:**
```javascript
// Just add 'ordered-select' class for auto-initialization
// Or call OrderedSelect.init() manually
```

#### 4. Use Blade Component

**Before:**
```blade
<select name="users[]" id="users" class="form-control select2" multiple>
    {{-- Complex logic for ordering options --}}
    @if(isset($selectedUsers))
        @foreach($selectedUsers as $userId)
            @if(isset($users[$userId]))
                <option value="{{ $userId }}" selected>{{ $users[$userId] }}</option>
            @endif
        @endforeach
    @endif
    @foreach($users as $id => $user)
        @if(!in_array($id, $selectedUsers ?? []))
            <option value="{{ $id }}">{{ $user }}</option>
        @endif
    @endforeach
</select>
```

**After:**
```blade
<x-kolydart::ordered-select
    name="users"
    :options="$users"
    :selected="$selectedUsers ?? []"
/>
```


## Private Media

`spatie/laravel-medialibrary` writes to the `public` disk by default, which is under the document root: those files answer a URL without PHP running, so no gate is ever consulted. Moving a collection to a private disk is only half the job — the other half is a route that serves it back with an authorization check in front, and that is what this component is.

Enable it in `config/kolydart.php`:

```php
'media' => [
    'enabled' => true,
    'routes'  => ['middleware' => ['web', 'auth', 'backend'], 'prefix' => 'admin', 'name' => 'admin.'],
    'public_collections' => ['ck-media', \App\Listing::class.'@image'],
    'gates' => [
        \App\Customer::class => 'customer_show',
        \App\Expense::class  => 'expense_show',
    ],
],
```

Then address files with `@mediaUrl($media)` / `@mediaUrl($media, 'thumb')` instead of `$media->getUrl()`. Both lookup tables take the same two key forms: `gates` is consulted as `Model@collection`, then `Model`, then `*`, and a model with no mapping is **denied**; `public_collections` accepts a bare collection name (every model) or `Model@collection` (one model), which is what a mixed decision needs when the same collection name is display material on one model and not on another. A listed collection whose files have since moved to a non-web-served disk falls back to the protected route with a logged warning, rather than producing a dead `/storage` link.

The gate abilities are checked **without the owning record**, so they express a class-level permission. If the application's own listings are scoped — multi-tenant, team-owned, granted per record — set `'access' => \App\Services\MyMediaAccess::class` to a class implementing `\Kolydart\Laravel\App\Support\MediaAccessContract` (one method, `allows(Media $media): bool`) and scope against `$media->model` there.

The route serves **local disks only**: it reads the file off the filesystem, which `Media::getPath()` can address only for the `local` driver. Media on S3 is protected by that bucket's ACL and a temporary URL, not by this route.

Full procedure — including the order the steps must be taken in, why the disk must move last, and the filesystem-permission trap that makes every moved file 404 — is in [`src/docs/private-media.md`](src/docs/private-media.md).

## Additional Components

### 1. AdminLteDevColor

Changes the AdminLTE sidebar color to a distinct blue (`#001FA1`) when the application environment is `local`. This helps distinguish development from production environments.

**Usage:**
```blade
<x-kolydart::admin-lte-dev-color />
```

### 2. Datatables

Auto-focuses the DataTables search input and hides the bulk delete button on index pages.

**Usage:**
```blade
<x-kolydart::datatables />
```

### 3. Edit Button

Raenders an "Edit" button if the current user has permission and the corresponding `edit` route exists for the resource.

**Usage:**
```blade
<x-kolydart::edit-button />
```
It automatically detects the current resource route (e.g., changes `show` to `edit`) and checks permissions using `Gate`.

### 4. Form Fields Size

Adds Bootstrap classes to form fields to standardize their size and layout.

**Usage:**
```blade
<x-kolydart::form-fields-size />
```

**Custom Class:**
```blade
<x-kolydart::form-fields-size class="col-md-4" />
```

### 5. Keyboard Shortcuts

Adds keyboard shortcuts for common actions:
- `Cmd/Ctrl + S`: Submit the form (Save).
- `Cmd/Ctrl + E`: Click the edit button.

**Usage:**
```blade
<x-kolydart::keyboard-shortcuts />
```

### 6. Language Switcher

Displays a link to switch key language. Requires `panel.available_languages` config.

**Usage:**
```blade
<x-kolydart::language-switcher />
```

**Configuration (`config/panel.php`):**
```php
'available_languages' => [
    'en' => 'English',
    'el' => 'Greek',
],
```

### 7. Message Display

Displays session messages returned by the controller (success, warning, error).

**Usage:**
```blade
<x-kolydart::message-display />
```

### 8. Save Button Danger To Primary

Automatically changes "Save" buttons with `btn-danger` class to `btn-primary`. Useful for standardizing button styles.

**Usage:**
```blade
<x-kolydart::save-button-danger-to-primary />
```

### 9. Signature

Displays a "developed by kolydart" signature.

**Usage:**
```blade
<x-kolydart::signature />
```

**With Copyright:**
```blade
<x-kolydart::signature :copyright="true" />
```

### 10. Table Style Reset

Resets Bootstrap table styles by removing `table-striped` and `table-bordered` classes.

**Usage:**
```blade
<x-kolydart::table-style-reset />
```

## Testing Helpers

### `InteractsWithDatatables`

Trait that closes a common test gap for `yajra/laravel-datatables`: ordinary feature tests hit the HTML shell of a `serverSide` DataTable but never trigger the AJAX request the browser fires for the actual rows. The helper extracts the live columns config from the rendered page and replays it as XHR with a global-search value, so server-side errors (invalid column names, broken filters, missing relations) surface in CI instead of in production.

Mix it into your project's base test case and call `assertDatatableAjaxLoads(string $route)` from any feature test:

```php
use Kolydart\Laravel\App\Testing\InteractsWithDatatables;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithDatatables;
}
```

```php
#[Test]
public function datatable_ajax_loads_without_errors(): void
{
    $this->login_user('Secretary');
    \App\Models\User::factory()->create();

    $this->assertDatatableAjaxLoads(route('admin.users.index'));
}
```

Full documentation, including what the helper catches and its limitations, is in [`src/docs/datatable-ajax-testing.md`](src/docs/datatable-ajax-testing.md).

### `InteractsWithSmokeCrawler`

Trait holding the shared machinery for the Laravel Dusk "browser smoke crawler" pattern: a test that visits every active GET page as a real browser and fails on browser-side errors (JS alerts, console SEVERE errors, rendered `.alert-danger`) that the headless PHPUnit suite cannot see. It provides route discovery, parametrized-URI resolution, the visit-and-assert loop, console-log filtering, and a deterministic page-settle wait — but not the `#[Test]` methods or `getAdminUser()`, which differ per project.

Mix it into `tests/Browser/SmokeTest.php` and declare the config it reads (all optional):

```php
use Kolydart\Laravel\App\Testing\InteractsWithSmokeCrawler;

class SmokeTest extends DuskTestCase
{
    use InteractsWithSmokeCrawler;

    protected array $skipNames = ['admin.logout', 'admin.users.massDestroy'];
    protected array $skipUriPrefixes = ['_debugbar', 'livewire/', 'api/'];
    protected array $ignoredConsolePatterns = ['/conversions/'];
    protected string $modelNamespace = 'App\\Models\\';

    // ... #[Test] methods + getAdminUser() ...
}
```

Console noise is filtered in two passes: `defaultIgnoredConsolePatterns()` drops browser-chrome artefacts wherever they match (and is merged with the project's `$ignoredConsolePatterns`), while `defaultIgnoredResourceHosts()` drops third-party asset hosts **only when the entry is also a load failure** — so a transient Google Fonts outage does not fail an unrelated route, but a CSP refusal naming that same host still does.

Full documentation, including the configuration contract and how to drop a shared default, is in [`src/docs/browser-smoke-testing.md`](src/docs/browser-smoke-testing.md).

## Testing

The package includes comprehensive tests. To run them:

```bash
cd vendor/kolydart/laravel
composer test
```

## License

GPL-3.0-or-later
