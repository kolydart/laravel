# kolydart/laravel

A collection of Laravel helper classes including ordered pivot relationships functionality.

## Table of Contents

- [Installation](#installation)
- [Ordered Pivot Relationships](#ordered-pivot-relationships)
  - [Quick Start](#quick-start)
  - [Components](#components)
  - [Usage Examples](#usage-examples)
  - [API Reference](#api-reference)
  - [Migration from Manual Implementation](#migration-from-manual-implementation)
- [Testing](#testing)
- [License](#license)

## Installation

```bash
composer require kolydart/laravel
```

The service provider will be automatically registered via Laravel's package auto-discovery.

## Ordered Pivot Relationships

This package provides functionality to maintain order in many-to-many (pivot) relationships. This abstraction allows you to preserve the selection order of related models, which is particularly useful for forms where the order of selection matters.

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

```blade
<script src="{{ asset('vendor/kolydart/js/ordered-select.js') }}"></script>
```

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
- `OrderedSelect.init()` - Auto-initialize all elements with 'ordered-select' class
- `OrderedSelect.getOrderedValues($select)` - Get selected values in order
- `OrderedSelect.setOrderedValues($select, values)` - Set values in specific order
- `OrderedSelect.addOption($select, value, text, selected, preserveOrder)` - Add new option
- `OrderedSelect.createAddForm($select, config)` - Create modal for adding options

#### 5. Blade Component: `<x-kolydart::ordered-select>`

Reusable component for ordered select fields.

#### 6. Livewire Component: `@livewire('kolydart-ordered-select')`

Advanced component with dynamic option addition and real-time updates.

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

## Testing

The package includes comprehensive tests. To run them:

```bash
cd vendor/kolydart/laravel
composer test
```

## License

GPL-3.0-or-later
