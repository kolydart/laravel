# Browser smoke testing (`InteractsWithSmokeCrawler`)

A smoke crawler visits every active GET page as a real browser and fails on browser-side errors the headless PHPUnit suite cannot see. Ordinary feature tests run without JavaScript and never fire the XHR that DataTables, Livewire, or PowerGrid depend on — so a change that breaks only the browser side passes the whole PHPUnit suite green and surfaces in the user's browser instead.

This trait holds the machinery that is identical across every installation: route discovery and filtering, parametrized-URI resolution, the visit-and-assert loop, and the deterministic page-settle wait. The project's `SmokeTest` keeps only what genuinely varies — the `#[Test]` methods, `getAdminUser()`, and the configuration properties below.

## What it provides

| Method | Purpose |
|---|---|
| `discoverRoutes(callable $nameMatcher, bool $allowParameters = false)` | Named GET routes matching `$nameMatcher`, minus the skip lists. Parametrized routes excluded unless `$allowParameters`. |
| `resolveUri(RoutingRoute $route)` | Substitutes the first record's key into a parametrized `admin.<resource>.<action>` URI. Returns `null` (a skip, not a failure) when the model class is missing or the table is empty. |
| `visitAndCollect(Browser $browser, RoutingRoute $route)` | Visits a route and runs the smoke assertions. Returns an error string, or `null` on success. |
| `visitUri(Browser $browser, string $name, string $uri)` | Same, for an already-resolved URI. |
| `significantConsoleErrors(array $entries)` | Reduces a raw browser log to the entries worth failing on. Pure string work, no WebDriver. |
| `waitForPageSettle(Browser $browser)` | Waits for `document.readyState` then `jQuery.active === 0`, with a longer settle for Livewire/Filament stacks. |

It does **not** provide `#[Test]` methods or `getAdminUser()` — those differ per project.

## Configuration

The trait declares no properties of its own, so it can never collide with a consuming class's declarations. It reads these, all optional:

```php
protected array $skipNames = [...];              // route names to never visit
protected array $skipUriPrefixes = [...];        // non-page URI prefixes
protected array $ignoredConsolePatterns = [...]; // noisy console substrings
protected string $modelNamespace = 'App\\';      // or 'App\\Models\\'
```

```php
use Kolydart\Laravel\App\Testing\InteractsWithSmokeCrawler;

class SmokeTest extends DuskTestCase
{
    use InteractsWithSmokeCrawler;

    protected array $skipNames = ['admin.logout', 'admin.users.massDestroy'];
    protected string $modelNamespace = 'App\\Models\\';

    // ... #[Test] methods + getAdminUser() ...
}
```

## The two ignore lists

Console noise is filtered in two passes with deliberately different semantics.

**`defaultIgnoredConsolePatterns()`** — browser-chrome artefacts (`favicon.ico`, `chrome-extension://`, `DevTools failed to load`). These drop a log entry **wherever** they match, because they are things the browser says about itself and carry no information about the application. The project's `$ignoredConsolePatterns` is merged on top of this list, so a project lists only what is specific to it.

**`defaultIgnoredResourceHosts()`** — third-party asset hosts (`fonts.googleapis.com`, `fonts.gstatic.com`, `google-analytics.com`, `googletagmanager.com`). These drop an entry **only when it is also a resource load failure** (`Failed to load resource`, `net::ERR_`). A smoke test measures the application, not the public internet: a transient `fonts.gstatic.com` `ERR_CONNECTION_CLOSED` would otherwise fail whichever route happened to be loading at that moment, reading as a regression on an unrelated page.

The load-failure condition is what keeps the host list honest. Matching a bare hostname would also swallow messages that merely *mention* the host — most importantly a CSP refusal such as `Refused to load the stylesheet 'https://fonts.googleapis.com/…' because it violates the following Content Security Policy directive`. That is an application misconfiguration which leaves fonts broken site-wide, and burying it is exactly the failure mode the crawler exists to prevent. The same applies to an app error whose stack frame quotes a third-party script URL.

For the same reason, **do not add generic network-error strings** (`ERR_CONNECTION_CLOSED`, `Failed to load resource`) to `$ignoredConsolePatterns`: they cover the application's own assets and XHR too.

## Dropping a shared default

Override the method. A trait method has no `parent::`, so alias it first rather than re-listing the whole array — a re-listed copy silently drifts from the package as defaults are added:

```php
use InteractsWithSmokeCrawler {
    defaultIgnoredConsolePatterns as packageIgnoredConsolePatterns;
}

protected function defaultIgnoredConsolePatterns(): array
{
    return array_diff($this->packageIgnoredConsolePatterns(), ['favicon.ico']);
}
```

## Notes

`resolveUri()` assumes the conventional `admin.<resource>.<action>` naming, a single `{param}` in the URI, and implicit binding on the primary key. A model overriding `getRouteKeyName()` needs a custom mapping for that resource.

Do **not** add `DatabaseMigrations` or `DatabaseTransactions` to a `SmokeTest`. The Dusk database is seeded once and persists; those traits would wipe the seed data between test methods and the crawler would report empty pages as passing.
