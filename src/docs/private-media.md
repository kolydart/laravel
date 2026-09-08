# Private media

Serving `spatie/laravel-medialibrary` files that must not answer an anonymous request.

## The problem this solves

The media library writes to the `public` disk by default — `disk_name => env('MEDIA_DISK', 'public')` — and that disk sits under the document root. A file there answers its URL without PHP ever running: no controller, no model scope, no gate. Authorisation applied anywhere in the application is irrelevant to it.

The leak does not usually arrive because somebody wrote the word `public`. It arrives because nobody wrote anything, and the default applied.

The obvious fix — point `media-library.disk_name` at a private disk — does not turn the links into protected links. It turns them into dead ones. Call sites address files with `$media->getUrl()`, and Laravel's local driver does not throw when a disk has no `url` key; it silently returns `/storage/{path}`, which corresponds to nothing. So the disk cannot move first.

**The order is: first the route, then the call sites, last the disk.**

## What the package provides

| Piece | Responsibility |
|---|---|
| `App\Http\Controllers\MediaController` | serves the original and each conversion, behind an access check |
| `App\Support\MediaAccessContract` | the one method a resolver must implement: `allows(Media $media): bool` |
| `App\Support\MediaAccess` | the default resolver — answers the question from a config lookup table |
| `App\Support\MediaUrl` | the only place that turns a `Media` row into a URL |
| `@mediaUrl($media)` / `@mediaUrl($media, 'thumb')` | the Blade form of `MediaUrl::url()` |
| `src/routes/media.php` | `{prefix}.media` and `{prefix}.media.conversion` |

The routes are registered by `KolydartServiceProvider` only when `kolydart.media.enabled` is true and `spatie/laravel-medialibrary` is installed — the package only suggests that dependency. The `@mediaUrl` directive is registered **unconditionally**, and on purpose: an unknown directive is not an error in Blade, it renders as the literal text `@mediaUrl($media)` in the page. Since step 3 below changes the call sites before the disk moves, a Blade using `@mediaUrl` while `enabled` is still false is an expected intermediate state, and it has to fail where somebody will see it.

**This route serves local disks only.** Both actions read the file straight off the filesystem, because `Media::getPath()` returns an absolute path only for the `local` driver; for a cloud disk it returns a path relative to the bucket, which no `is_file()` will ever find — so every request would 404, with the same misleading symptom as the permissions trap below. A private collection belongs on a local disk rooted outside the document root. Media on S3 is protected by that bucket's own ACL and a temporary URL instead, which is a different mechanism and not this one.

## Configuration

```php
// config/kolydart.php
'media' => [
    'enabled' => true,

    'routes' => [
        'middleware' => ['web', 'auth', 'backend'],
        'prefix'     => 'admin',
        'name'       => 'admin.',
    ],

    'conversions' => ['thumb', 'preview'],

    'public_collections' => ['ck-media', \App\Listing::class.'@image'],

    'gates' => [
        \App\Customer::class    => 'customer_show',
        \App\Expense::class     => 'expense_show',
        \App\Reservation::class => 'reservation_show',
    ],
],
```

`gates` is consulted with three keys, most specific first: `Model@collection`, then `Model`, then `*`. A mapped ability of `null` admits any authenticated user. **A model with no mapping and no `*` fallback is denied** — a collection added after the config was written is private until someone says otherwise, which is the failure direction that does not leak.

**The ability is checked without the owning record.** `Gate::allows('customer_show')` asks a class-level question — "may this user view customers?" — not a record-level one. In an application whose own listings are unscoped, that matches what its `CustomerController@show` already enforces and there is nothing more to say. In an application whose listings *are* scoped — multi-tenant, team-owned, granted per record — a gate name cannot express the scope, and leaving it at that hands every holder of `customer_show` every customer's files.

That is what the resolver is for. Replace it with `'access' => \App\Services\MyMediaAccess::class` to express rules a gate name cannot carry: an explicit per-record grant, an embargo date, access inherited from a parent record. It must implement `\Kolydart\Laravel\App\Support\MediaAccessContract`, which is the single method `allows(Media $media): bool` — nothing needs to be inherited from the default resolver, and `$media->model` is there to scope against.

`public_collections` takes the same two key forms: a bare collection name covers that collection on every model, `Model@collection` covers one model's. The qualified form is what a mixed decision needs — the same collection name can be display material on one model and confidential on another, and a bare entry would open both.

Being listed is necessary but not sufficient: **the disk the files actually sit on has the last word.** A collection left in `public_collections` after its files moved would otherwise get `getUrl()` and the dead `/storage/{path}` this whole component exists to prevent, and a stale config is precisely the case that cannot be trusted. Such media falls through to the protected route instead — which resolves the file from the media's own `disk` column and therefore still works — and logs a warning naming the collection and the disk. A cloud disk still counts as web-served without a `url` key, because only the local driver invents a URL that leads nowhere.

Everything not named in `public_collections` is addressed through the protected route **whatever disk it currently sits on**. That is what lets the disk move last: links keep working while the files are still on the old disk, so the route and the migration can ship in either order.

## Four things that go wrong

**The conversions are half the file.** Moving an original to a private disk while its `thumb` stays behind protects nothing: a 120px preview of an identity document is still the document. This is why `media.conversion` exists and why it asks the same question the original does.

**`generated_conversions` is not the authority; the disk is.** Older versions of the package never wrote that column, so rows whose conversion files are present on disk can still report none. A controller that gates on the column hides files that exist. Both actions ask `is_file()` instead, and answer 404 — after the gate, so the response never tells an unauthorised caller whether a file exists.

**Declaring a disk does not move what is already uploaded.** `useDisk('local')` applies to the next upload. Existing rows keep their `disk` and `conversions_disk` values and their files stay where they are. Moving them takes a migration, and it must copy first and delete last, so an interruption leaves the file on two disks rather than none.

**Private visibility defaults to 0700/0600.** Flysystem's default for a private disk means "whoever wrote it". That holds while one process writes and reads, and breaks the moment something writes from the command line — a deploy running as one user, PHP-FPM as another. A directory at `0700 deploy:deploy` is not even traversable by the web server, `is_file()` answers false for a path you cannot reach, and every file 404s as though it were missing. Declare the permissions explicitly on the disk:

```php
'local' => [
    'driver' => 'local',
    'root'   => storage_path('app'),
    'throw'  => false,
    'permissions' => [
        'file' => ['public' => 0664, 'private' => 0664],
        'dir'  => ['public' => 0771, 'private' => 0771],
    ],
],
```

Nothing is weakened by this: the protection is that the disk is not served by the web server, not the mode bits. Directories give "other" only `--x` — traversal, not enumeration.

## Applying it to an application

1. Give the app a private disk — `local` driver, rooted outside the document root — with explicit `permissions` (above).
2. Fill in `kolydart.media.gates` and `public_collections`, set `enabled`.
3. Replace every `$media->getUrl()` in Blades with `@mediaUrl($media)` / `@mediaUrl($media, 'thumb')`. This is the step that must precede the disk change.
4. Declare the collections in `registerMediaCollections()` with `->useDisk(...)`, so new uploads land privately.
5. Write the migration that moves the existing files and updates `disk` / `conversions_disk` — copy, update, then delete.
6. Verify on the server that PHP-FPM can read what the migration wrote.

## Serving semantics

The original is sent inline only for `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/avif` and `application/pdf`; everything else is an attachment. `text/html` and `image/svg+xml` execute in the browser, and a file uploaded by one user and rendered inline from the application's own origin is stored XSS against every other user who opens it.

**The disposition and the `Content-Type` header come from the same value**, which is not a detail. Left to itself, `BinaryFileResponse` sets `Content-Type` from a fresh `finfo` guess over the file's *contents*, while the inline-or-attachment decision reads the `mime_type` column — and the two need not agree, since Flysystem falls back to extension-based detection for inconclusive types and Symfony does not. The controller therefore sets the header explicitly from the value it just judged safe, and adds `X-Content-Type-Options: nosniff` so the browser cannot reopen the question.

A conversion has no mime type of its own to read — the column describes the original, and a conversion may be a different format — so its type is taken from the extension the converter wrote, against a safe list of image formats. An extension outside that list is served as an opaque download rather than inline.

The conversion name reaches a filesystem path, so it is constrained to `kolydart.media.conversions` at the router rather than checked in the controller. That list is global, though, and whether *this* model registers *that* conversion is a separate question which the media library answers by throwing `InvalidConversion`. A conversion the model never declared is a missing file, not a server fault, so the controller catches it and answers 404 — after the gate, like every other 404 here.
