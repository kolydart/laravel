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
 * `$ignoredConsolePatterns` is merged on top of `defaultIgnoredConsolePatterns()`,
 * so a project lists only what is specific to it. A second list,
 * `defaultIgnoredResourceHosts()`, suppresses third-party hosts only when the
 * entry is also a load failure — see those methods for the reasoning.
 *
 * To drop one of the shared defaults, override the method. A trait method has no
 * `parent::`, so alias it first rather than re-listing the whole array, which
 * would silently drift from the package:
 *
 * ```php
 * use InteractsWithSmokeCrawler {
 *     defaultIgnoredConsolePatterns as packageIgnoredConsolePatterns;
 * }
 *
 * protected function defaultIgnoredConsolePatterns(): array
 * {
 *     return array_diff($this->packageIgnoredConsolePatterns(), ['favicon.ico']);
 * }
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
        $logs = $this->significantConsoleErrors($browser->driver->manage()->getLog('browser'));

        if ($logs->isNotEmpty()) {
            fwrite(STDOUT, "FAIL (console errors)\n");

            // Not pluck('message'): WebDriver has been seen to return entries with no
            // message key, which would render as an empty bullet — a failure with no
            // way to act on it. Fall back to the raw entry.
            $rendered = $logs->map(fn ($entry) => $entry['message'] ?? json_encode($entry));

            return "Route {$name} ({$uri}): console errors:\n  - ".$rendered->implode("\n  - ");
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
     * Reduce a raw browser log to the entries a smoke run should fail on: SEVERE
     * level, minus the ignored ones.
     *
     * Two filters, deliberately not one. An unconditional pattern drops the entry
     * wherever it matches; a third-party host only drops it when the entry is also
     * a *resource load failure*. See `defaultIgnoredResourceHosts()` for why the
     * distinction is load-bearing.
     *
     * Kept free of the WebDriver so the filtering contract is unit-testable
     * without a browser.
     *
     * @param  array<int, array{level?: string, message?: string}>  $entries
     * @return Collection<int, array{level?: string, message?: string}>
     */
    protected function significantConsoleErrors(array $entries): Collection
    {
        $ignored = array_unique(array_merge(
            $this->defaultIgnoredConsolePatterns(),
            $this->ignoredConsolePatterns ?? []
        ));
        $hosts = $this->defaultIgnoredResourceHosts();

        // Chrome's wording when a resource never arrived, as opposed to any other
        // reason its URL might appear in a message (a CSP refusal, a stack frame,
        // an app-thrown error quoting it).
        $loadFailure = ['Failed to load resource', 'net::ERR_'];

        return collect($entries)
            ->where('level', 'SEVERE')
            ->reject(function ($entry) use ($ignored, $hosts, $loadFailure) {
                $message = $entry['message'] ?? '';

                if (Str::contains($message, $ignored)) {
                    return true;
                }

                return Str::contains($message, $hosts)
                    && Str::contains($message, $loadFailure);
            })
            ->values();
    }

    /**
     * Console noise every installation ignores unconditionally, merged with the
     * project's own `$ignoredConsolePatterns`. Declared as a method, not a
     * property, so the trait introduces no properties of its own and can never
     * collide with a consuming class's declaration.
     *
     * Browser-chrome artefacts only: things the browser says about itself, which
     * carry no information about the application under test.
     *
     * Do NOT add generic network-error strings (`ERR_CONNECTION_CLOSED`,
     * `Failed to load resource`) here: those also cover the application's own
     * assets and XHR, which is exactly what the crawler exists to catch.
     *
     * @return array<int, string>
     */
    protected function defaultIgnoredConsolePatterns(): array
    {
        return [
            'favicon.ico',
            'chrome-extension://',
            'DevTools failed to load',
        ];
    }

    /**
     * Third-party asset hosts whose *failed requests* are ignored, because a smoke
     * test measures the application, not the public internet — a transient
     * `fonts.gstatic.com` ERR_CONNECTION_CLOSED would otherwise fail whichever
     * route happened to be loading at the time, reading as a regression on an
     * unrelated page.
     *
     * Matched only in combination with a load-failure marker, never on the
     * hostname alone. The difference is not academic: an app that ships a CSP
     * blocking its own Google Fonts stylesheet logs
     * `Refused to load the stylesheet 'https://fonts.googleapis.com/…'` — an
     * application misconfiguration that a bare hostname match would bury, leaving
     * fonts broken site-wide with a green suite.
     *
     * @return array<int, string>
     */
    protected function defaultIgnoredResourceHosts(): array
    {
        return [
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'google-analytics.com',
            'googletagmanager.com',
        ];
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
