# DataTable AJAX Testing Helper

> **Document Purpose**: Reference for `InteractsWithDatatables`, a feature-test helper that exercises the AJAX endpoint of a Yajra-DataTable index page — closing the gap where ordinary `$this->get(route(...))` tests only cover the HTML shell.

## Table of Contents

- [Why it exists](#why-it-exists)
- [What it does](#what-it-does)
- [Setup](#setup)
- [Usage](#usage)
- [What it catches](#what-it-catches)
- [Limitations](#limitations)

## Why it exists

A typical feature test for an admin index page looks like this:

```php
$response = $this->get(route('admin.users.index'));
$response->assertSuccessful();
```

For DataTables configured with `serverSide: true`, the page returned by this call is just the table shell — the actual row data is fetched by a **separate XHR request** the browser fires after the page loads. That request hits the same URL but with `?draw=…&columns[…]&search=…` parameters. Your `get()` call never triggers it, so server-side errors (broken filters, missing joins, invalid column names, etc.) never reach your assertions.

A real example: `yajra/laravel-datatables-oracle` v12 introduced an allowlist validator on `columns[N][name]` (`/^[a-zA-Z0-9_.\-> ]+$/`) that rejects non-ASCII column names. A common anti-pattern — `name: '{{ trans('global.actions') }}'` — renders as `Ενέργειες` under a Greek locale and crashes the AJAX call. Feature tests stayed green; the live page broke for every user.

## What it does

`assertDatatableAjaxLoads(string $route, string $search = '__datatable_ajax_probe__')`:

1. **Fetches the index HTML** at `$route` and asserts it is 2xx. A redirect to login or a 403 fails here with a clear message instead of leaking through as a confusing "no column config found" failure later on.
2. **Extracts the live columns config** by regex-matching each column block in the rendered page and reading its `data`, `name`, and the optional `searchable` / `orderable` flags. Mirroring the real per-column flags means the replayed payload matches what the browser would send rather than forcing every column searchable/orderable.
3. **Re-issues the request as XHR** with:
   - `X-Requested-With: XMLHttpRequest` (so `$request->ajax()` returns true)
   - `Accept: application/json`
   - A global `search.value` (the `$search` parameter, defaulting to the sentinel `__datatable_ajax_probe__` so it is unlikely to match real rows) and an `order[0]` clause pinned to the first orderable column, so Yajra calls `getColumnName()` on every searchable column.
   - The query string is appended with `?` or `&` depending on whether `$route` already carries parameters.
4. **Asserts** the response is 2xx AND its JSON body contains **no `error` key**. The latter is critical: Yajra v9+ catches server-side exceptions and serialises them as `{"error": "Exception Message: …"}` with status 200, which the browser renders as a `DataTables warning:` alert. A naive `assertSuccessful()` lets these through.

Returns the `TestResponse` for further assertions. Pass a custom `$search` value if you want the probe to hit real `filterColumn` logic (e.g. an existing record's name).

## Setup

The helper is shipped as a PHP trait (no service provider, no auto-discovery hook). Include it in your project's base test case:

```php
// tests/TestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Kolydart\Laravel\App\Testing\InteractsWithDatatables;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use InteractsWithDatatables;
}
```

The trait calls `get()`, `withHeaders()`, `assertNotEmpty()`, `assertIsArray()`, and `assertArrayNotHasKey()` — all standard Laravel/PHPUnit methods — so any test class that already extends `Illuminate\Foundation\Testing\TestCase` (or `Tests\TestCase`) can use it without additional dependencies.

## Usage

```php
use PHPUnit\Framework\Attributes\Test;

class UsersControllerTest extends TestCase
{
    #[Test]
    public function datatable_ajax_loads_without_errors(): void
    {
        $this->login_user('Secretary');
        \App\Models\User::factory()->create();

        $this->assertDatatableAjaxLoads(route('admin.users.index'));
    }
}
```

You typically need exactly **one** test per index page that uses `serverSide` DataTables. The helper is permission-aware: the authenticated user must be able to load both the HTML page (no 302/403) and the AJAX endpoint. Seed any default data the controller needs (e.g. role records, lookup tables) in the test or `TestCase::setUp()`.

## What it catches

- **Invalid column names** (the original motivator): `name: '<translated>'` triggering `InvalidArgumentException("Invalid column name: \"…\".")` from `DataTableAbstract::getColumnName()`.
- **Broken eager loads or join clauses** invoked only on AJAX (e.g. `with(['roles'])->select(...)`).
- **Missing accessors / relations** referenced in `editColumn()` callbacks when at least one row exists.
- **Permission gate regressions** on the AJAX branch separately from the HTML branch (some controllers re-check gates inside `if ($request->ajax())`).
- **Search/order filter regressions** in custom `filterColumn()` / `orderColumn()` callbacks, because the helper sends both a global-search value and an `order[0]` clause.

## Limitations

- **HTML extraction is regex-based.** The helper looks for column-object blocks containing literal `data: '…'` and `name: '…'` pairs (with optional `searchable`/`orderable` flags). Unusual formatting (e.g. nested braces inside a column object, computed names, JSON parsed at runtime) will not match — extend the regex or pass the columns explicitly if you hit this.
- **Doesn't cover non-Yajra tables.** If the view actually renders a Livewire-PowerGrid component (or any other table that doesn't expose DataTables JS columns), the regex will return no matches and the test will fail with `No DataTables column config found in HTML for route: …`. Remove the test or write a PowerGrid-specific one in that case.
- **Doesn't validate the rendered cells.** The helper asserts the endpoint responds cleanly; it does not assert the data shape (use additional assertions on the returned `TestResponse` if needed — e.g. `$response->assertJsonPath('recordsTotal', 1)`).
- **Doesn't replace browser tests.** It will not catch JavaScript errors, CSS issues, or DataTables-side rendering bugs. For full coverage of those, use Playwright / Dusk in addition.
