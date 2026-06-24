<?php

namespace Kolydart\Laravel\App\Testing;

use Facebook\WebDriver\Exception\NoSuchAlertException;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;

/**
 * Reusable plumbing for the Laravel Dusk "browser smoke crawler" pattern.
 *
 * A smoke crawler visits every active GET page as a real browser and fails on
 * browser-side errors (JS alerts, console SEVERE errors, rendered .alert-danger)
 * that the headless PHPUnit suite cannot see — DataTables / Livewire / PowerGrid
 * XHR, JS config mistakes, asset loading, etc.
 *
 * This trait extracts the parts that are identical across every installation:
 * route discovery + filtering, parametrized-URI resolution, the visit-and-assert
 * loop, and the deterministic page-settle wait. The project's `SmokeTest` keeps
 * only what genuinely varies between apps: the `#[Test]` methods (which route
 * patterns / frontend strategy to crawl), `getAdminUser()`, and the config below.
 *
 * The consuming test class is expected to declare these properties (all optional —
 * sensible defaults apply when absent):
 *
 * ```php
 * protected array $skipNames = [...];              // route names to never visit
 * protected array $skipUriPrefixes = [...];        // non-page URI prefixes
 * protected array $ignoredConsolePatterns = [...]; // noisy console substrings
 * protected string $modelNamespace = 'App\\';      // or 'App\\Models\\'
 * ```
 *
 * Usage (in `tests/Browser/SmokeTest.php`):
 *
 * ```php
 * use Kolydart\Laravel\App\Testing\InteractsWithSmokeCrawler;
 *
 * class SmokeTest extends DuskTestCase
 * {
 *     use InteractsWithSmokeCrawler;
 *
 *     protected array $skipNames = [...];
 *     // ... #[Test] methods + getAdminUser() ...
 * }
 * ```
 *
 * @see https://github.com/laravel/dusk
 */
trait InteractsWithSmokeCrawler
{
    /**
     * Discover named GET routes whose name matches $nameMatcher, minus the
     * configured skip lists. Parametrized routes are excluded unless
     * $allowParameters is true (used by the show/edit crawlers).
     */
    protected function discoverRoutes(callable $nameMatcher, bool $allowParameters = false): Collection
    {
        $skipNames = $this->skipNames ?? [];
        $skipUriPrefixes = $this->skipUriPrefixes ?? [];

        return collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->filter(fn ($r) => $r->getName() !== null)
            ->filter(fn ($r) => $nameMatcher($r->getName()))
            ->filter(fn ($r) => $allowParameters || ! str_contains($r->uri(), '{'))
            ->filter(fn ($r) => ! in_array($r->getName(), $skipNames, true))
            ->filter(fn ($r) => ! Str::startsWith($r->uri(), $skipUriPrefixes))
            ->values();
    }

    /**
     * Resolve a parametrized admin route URI by substituting the first record's key.
     *
     * Assumes the conventional `admin.<resource>.<action>` naming and that
     * `<resource>` maps to `{$modelNamespace}` + StudlySingular (e.g.
     * `session-types` → `App\SessionType`). Returns null when the model class is
     * missing or the table is empty — a skip, not a failure.
     */
    protected function resolveUri(RoutingRoute $route): ?string
    {
        $name = $route->getName();
        $segments = explode('.', $name);

        if (count($segments) < 3) {
            return null;
        }

        $resource = $segments[1];
        $class = ($this->modelNamespace ?? 'App\\').Str::studly(Str::singular($resource));

        if (! class_exists($class)) {
            fwrite(STDOUT, "  → {$name} ({$route->uri()}) ... skipped (no model {$class})\n");

            return null;
        }

        $record = $class::first();

        if (! $record) {
            fwrite(STDOUT, "  → {$name} ({$route->uri()}) ... skipped (no record in ".class_basename($class).")\n");

            return null;
        }

        $uri = preg_replace('/\{[^}]+\}/', (string) $record->getKey(), $route->uri(), 1);

        return '/'.ltrim($uri, '/');
    }

    /** Returns an error message string on failure, or null on success. */
    protected function visitAndCollect(Browser $browser, RoutingRoute $route): ?string
    {
        return $this->visitUri($browser, $route->getName() ?? $route->uri(), '/'.ltrim($route->uri(), '/'));
    }

    /** Visits an absolute URI and runs the smoke assertions. */
    protected function visitUri(Browser $browser, string $name, string $uri): ?string
    {
        fwrite(STDOUT, "  → {$name} ({$uri}) ... ");

        try {
            $browser->visit($uri);
            $this->waitForPageSettle($browser);
        } catch (\Throwable $e) {
            fwrite(STDOUT, "ERROR\n");

            return "Route {$name} ({$uri}): visit threw ".get_class($e).': '.$e->getMessage();
        }

        // 1. Browser-level alert (DataTables warnings, custom JS alerts)
        try {
            $alertText = $browser->driver->switchTo()->alert()->getText();
            $browser->driver->switchTo()->alert()->accept();
            $browser->driver->manage()->getLog('browser'); // drain so logs don't leak to next route

            return "Route {$name} ({$uri}): unexpected browser alert: {$alertText}";
        } catch (NoSuchAlertException $e) {
            // expected — no alert
        }

        // 2. Console SEVERE errors
        $ignored = $this->ignoredConsolePatterns ?? [];
        $logs = collect($browser->driver->manage()->getLog('browser'))
            ->where('level', 'SEVERE')
            ->reject(function ($entry) use ($ignored) {
                foreach ($ignored as $pattern) {
                    if (Str::contains($entry['message'] ?? '', $pattern)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        if ($logs->isNotEmpty()) {
            fwrite(STDOUT, "FAIL (console errors)\n");

            return "Route {$name} ({$uri}): console errors:\n  - ".$logs->pluck('message')->implode("\n  - ");
        }

        // 3. Rendered server-error indicators
        try {
            $browser->assertMissing('.alert-danger');
        } catch (\Throwable $e) {
            fwrite(STDOUT, "FAIL (.alert-danger)\n");

            return "Route {$name} ({$uri}): rendered .alert-danger on page";
        }

        fwrite(STDOUT, "ok\n");

        return null;
    }

    /**
     * Wait for the page to finish loading and any client-side data fetch to settle
     * before the console log is drained.
     *
     * A fixed pause races against client-rendered tables: server-side DataTables
     * fire their own XHR after DOM ready, so on a slow (cold) run the response —
     * and any error it logs — arrives after we drain the log, leaking into the
     * next route. Waiting for jQuery.active to reach 0 drains at a deterministic
     * point and attributes errors to the correct route, WITHOUT hiding genuine
     * errors (a request that truly fails still logs its SEVERE entry).
     *
     * Stack-aware:
     *  - jQuery/DataTables apps wait for jQuery.active === 0.
     *  - Livewire/Filament apps (no jQuery) get a slightly longer settle so the
     *    component can hydrate after its initial server-side render.
     */
    protected function waitForPageSettle(Browser $browser): void
    {
        try {
            $browser->waitUntil('document.readyState === "complete"', 10);

            // Let any table initialise and fire its request before checking idleness —
            // otherwise jQuery.active is briefly 0 pre-flight.
            $browser->pause(400);

            // Pages without jQuery resolve immediately; AJAX pages wait for the XHR.
            $browser->waitUntil('(typeof window.jQuery === "undefined") || window.jQuery.active === 0', 10);

            // Livewire/Filament (no jQuery counter) needs a touch longer to hydrate.
            $isLivewire = (bool) $browser->driver->executeScript('return typeof window.Livewire !== "undefined";');
            $browser->pause($isLivewire ? 700 : 300);
        } catch (\Throwable $e) {
            // Timeout (e.g. a hung request) — fall through to the smoke assertions,
            // which still surface genuine console/render errors.
        }
    }
}
