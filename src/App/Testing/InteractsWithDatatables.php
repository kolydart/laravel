<?php

namespace Kolydart\Laravel\App\Testing;

use Illuminate\Testing\TestResponse;

/**
 * Test helper for Yajra Laravel-DataTables (`yajra/laravel-datatables-oracle`).
 *
 * @deprecated Use Laravel Dusk smoke tests instead. Feature-test AJAX replays
 *             are brittle against JS-rendered tables and miss real browser
 *             behaviour. A Dusk test drives an actual browser, executes JS,
 *             and catches the same failures without fragile HTML scraping.
 *
 * Standard feature tests that hit the index route only exercise the HTML
 * shell of a DataTable. They never trigger the AJAX endpoint that
 * `serverSide: true` DataTables call from the browser on page load, so
 * server-side errors (invalid column names, broken filters, missing
 * relations, etc.) pass tests but fail in production.
 *
 * `assertDatatableAjaxLoads()` replays the browser's request:
 *
 *  1. Fetches the index HTML (asserting it is 2xx — a redirect to login
 *     or a 403 fails here with a clear message instead of leaking through
 *     as "no column config found").
 *  2. Extracts the live column config the JS would use, including each
 *     column's `searchable` and `orderable` flags when present, so the
 *     replayed request mirrors what the browser actually sends.
 *  3. Re-issues that config as an XHR request with a global-search value
 *     and an `order` clause pinned to the first orderable column, which
 *     forces Yajra's `DataTableAbstract::getColumnName()` validator to
 *     run against every searchable column.
 *  4. Asserts the response is 2xx AND that the JSON body has no `error`
 *     key — Yajra catches server-side exceptions and returns them as
 *     `200 OK` with `{"error": "…"}`, which the browser then renders as
 *     a DataTables alert. Without the body check, failures slip through.
 *
 * Note: only Yajra-style DataTables that emit a `{ data: '…', name: '…' }`
 * column config in the page source are supported. Livewire/PowerGrid and
 * other table libraries will trip the "no column config found" assertion.
 *
 * Usage (in your project's `tests/TestCase.php`):
 *
 * ```php
 * use Kolydart\Laravel\App\Testing\InteractsWithDatatables;
 *
 * abstract class TestCase extends BaseTestCase
 * {
 *     use InteractsWithDatatables;
 * }
 * ```
 *
 * Then in any feature test:
 *
 * ```php
 * #[Test]
 * public function datatable_ajax_loads_without_errors(): void
 * {
 *     $this->login_user('Secretary');
 *     \App\Models\User::factory()->create();
 *
 *     $this->assertDatatableAjaxLoads(route('admin.users.index'));
 * }
 * ```
 *
 * The helper requires the test class to extend Laravel's
 * `Illuminate\Foundation\Testing\TestCase` (or `Tests\TestCase`) so that
 * `get()`, `withHeaders()`, and assertion methods are available.
 *
 * @see https://github.com/yajra/laravel-datatables
 */
trait InteractsWithDatatables
{
    /**
     * Replay the AJAX request that a Yajra-DataTable index view issues
     * from the browser, and assert it completes without an error payload.
     *
     * @deprecated Use a Laravel Dusk smoke test instead.
     *
     * @param  string  $route   Fully-qualified URL of the index page (use `route(…)`).
     * @param  string  $search  Global-search probe value sent on the XHR. The
     *                          default is a sentinel unlikely to match real rows;
     *                          override only if you want to exercise a real
     *                          `filterColumn` hit.
     * @return TestResponse     The XHR response, for further assertions.
     */
    public function assertDatatableAjaxLoads(string $route, string $search = '__datatable_ajax_probe__'): TestResponse
    {
        $htmlResponse = $this->get($route);
        $htmlResponse->assertSuccessful();
        $html = $htmlResponse->getContent();

        preg_match_all('/\{[^{}]*\bdata:[^{}]*\}/', $html, $blockMatches);

        $columns = [];
        foreach ($blockMatches[0] as $block) {
            if (! preg_match('/\bdata:\s*[\'"]([^\'"]+)[\'"]/', $block, $dataMatch)) {
                continue;
            }
            if (! preg_match('/\bname:\s*[\'"]([^\'"]+)[\'"]/', $block, $nameMatch)) {
                continue;
            }

            $searchable = preg_match('/\bsearchable:\s*(true|false)\b/', $block, $m) ? $m[1] : 'true';
            $orderable = preg_match('/\borderable:\s*(true|false)\b/', $block, $m) ? $m[1] : 'true';

            $columns[] = [
                'data' => $dataMatch[1],
                'name' => $nameMatch[1],
                'searchable' => $searchable,
                'orderable' => $orderable,
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        $this->assertNotEmpty(
            $columns,
            "No DataTables column config found in HTML for route: {$route}"
        );

        $orderColumn = 0;
        foreach ($columns as $i => $col) {
            if ($col['orderable'] === 'true') {
                $orderColumn = $i;
                break;
            }
        }

        $payload = [
            'draw' => 1,
            'columns' => $columns,
            'order' => [['column' => $orderColumn, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'search' => ['value' => $search, 'regex' => 'false'],
        ];

        $separator = str_contains($route, '?') ? '&' : '?';

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($route . $separator . http_build_query($payload));

        $response->assertSuccessful();

        $body = $response->json();
        $this->assertIsArray($body, "DataTable AJAX did not return JSON: {$route}");

        if (is_array($body) && array_key_exists('error', $body)) {
            $this->fail('DataTable AJAX returned an error: ' . $body['error']);
        }

        return $response;
    }
}
