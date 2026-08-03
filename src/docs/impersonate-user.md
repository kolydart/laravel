## Table of Contents

- [UI-based Impersonation](#ui-based-impersonation)
  - [How It Works](#how-it-works)
  - [Setup](#setup)
  - [Routes](#routes)
  - [Security](#security)


## UI-based Impersonation

Allows an Admin to log in as another user directly from the UI, without knowing their password. Uses session storage to track the original admin so they can return at any time.

### How It Works

1. Admin visits a user's show page and clicks "Impersonate".
2. The current admin ID and start timestamp are stored in the session under `impersonating_admin_id`.
3. `Auth::login($targetUser)` switches the active session to the target user.
4. A banner in the layout shows the impersonation is active and provides a "Leave" button.
5. On leave, the session key is read to restore the original admin.
6. The session expires automatically after `ttl_seconds` (default: 1 hour).

### Setup

#### 1. Create the permission (required)

Add `user_impersonate` to your `PermissionsTableSeeder`:

```php
['id' => 2034, 'title' => 'user_impersonate', 'comments' => 'Log in as another user (impersonation)'],
```

Then assign it to the Admin role in `PermissionRoleTableSeeder` and run:

```bash
php artisan db:seed --class=PermissionsTableSeeder
php artisan db:seed --class=PermissionRoleTableSeeder
```

> **This permission must exist in the database.** The `ImpersonateController` calls `Gate::denies('user_impersonate')` — without the permission row, all requests will receive a `403 Forbidden`.

#### 2. Add the "Impersonate" button to the user show view

```blade
@can('user_impersonate')
    @if($user->id !== auth()->id())
        <form action="{{ route('admin.users.impersonate', $user->id) }}" method="POST" style="display: inline-block;">
            @csrf
            <button type="submit" class="btn btn-warning">Impersonate</button>
        </form>
    @endif
@endcan
```

#### 3. Add the "Leave Impersonation" banner to the admin layout

Add this inside the navbar's right `<ul class="navbar-nav ml-auto">`:

```blade
@if(session('impersonating_admin_id'))
    <li class="nav-item d-flex align-items-center mr-3">
        <span class="badge badge-warning mr-2">
            <i class="fa fa-user-secret mr-1"></i>
            Impersonating: {{ auth()->user()->name }}
        </span>
        <form action="{{ route('admin.users.leaveImpersonation') }}" method="POST" class="mb-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-danger">
                <i class="fa fa-sign-out-alt mr-1"></i> Leave
            </button>
        </form>
    </li>
@endif
```

### Routes

The package registers two POST routes automatically via `KolydartServiceProvider`:

| Method | URI | Name |
|--------|-----|------|
| POST | `admin/users/{user}/impersonate` | `admin.users.impersonate` |
| POST | `admin/users/leave-impersonation` | `admin.users.leaveImpersonation` |

Route prefix, name prefix, middleware, and redirects are configurable via `config/kolydart.php`:

```php
'impersonate' => [
    'admin_role_id' => 1,              // Role ID that counts as "admin"
    'session_key'   => 'impersonating_admin_id',
    'ttl_seconds'   => env('IMPERSONATE_TTL_SECONDS', 3600),
    'auto_register_timeout' => true,   // append the TTL middleware to 'web'
    'redirect_to'      => null,                 // null => auto (see below)
    'redirect_back_to' => 'admin.users.index',  // after leaving impersonation
    'routes' => [
        'middleware'       => ['web', 'auth', '2fa', 'backend'],
        'leave_middleware' => null,             // null => same as 'middleware'
        'prefix'           => 'admin',
        'name'             => 'admin.',
    ],
],
```

> **If you publish `config/kolydart.php`, publish it whole.** `mergeConfigFrom()` merges only the top level, so a published file containing an `impersonate` key replaces the package's entire sub-array — any key you omit is missing, not defaulted. In particular, omitting `routes` drops the middleware stack; the provider then falls back to `['web', 'auth']`, which keeps CSRF protection but loses `2fa` and `backend`. After upgrading the package, diff your published config against `src/config/kolydart.php`.

#### Landing page after impersonation starts

`redirect_to` accepts a route name. When left `null`, the target is resolved automatically: users **without** backend access are sent to `frontend.home`, everyone else to `admin.home`. Unknown route names fall back to `/`.

This matters because most impersonation targets are ordinary users: sending them to `admin.home` would hit the backend middleware and produce a 403 immediately after impersonation starts.

#### Middleware for the leave route

The "Leave" request is issued by the **impersonated** user, not by the admin. If the shared `middleware` stack contains `backend` (or any other admin-only gate), that user is denied and can never return to their admin session. Set `leave_middleware` to a stack they can pass:

```php
'routes' => [
    'middleware'       => ['web', 'auth'],
    'leave_middleware' => ['web', 'auth'],
],
```

#### Blocking specific users

If the user model defines a `canBeImpersonated(): bool` method, it is consulted before impersonation starts and a `false` return aborts with 403. Use it to exclude accounts that would break the session — e.g. unverified users in apps whose middleware logs them out:

```php
public function canBeImpersonated(): bool
{
    return (bool) $this->verified;
}
```

### Security

- **Admin-only**: the initiator must hold the `user_impersonate` Gate permission **and** belong to the configured `admin_role_id` — both checks are required.
- **No nested impersonation**: a second impersonate request while a session is already active returns HTTP 409.
- **No admin→admin escalation**: impersonating a user who is also an admin is blocked (HTTP 403).
- **No self-impersonation**: `abort_if($user->id === auth()->id(), 403)`.
- **Revoked-admin guard**: `leaveImpersonation` re-checks the admin's role before restoring the session — if the role was removed, the session is destroyed and the user is redirected to login.
- **TTL enforcement**: sessions expire after `ttl_seconds` (default: 3600 s). `EnforceImpersonationTimeout` is appended to the `web` middleware group automatically (see below).
- **Audit log**: every start/end event is written to `AuditLog` (if the model exists in `App\Models\AuditLog` or `App\AuditLog`).
- `leaveImpersonation` aborts with 403 if no session key exists (prevents direct URL access).
- Both routes require the configured middleware stack (default: `web`, `auth`, `2fa`, `backend`).

#### Timeout Middleware

`KolydartServiceProvider` appends `EnforceImpersonationTimeout` to the `web` middleware group on boot. No manual registration is needed. The middleware returns immediately for any request without an impersonation session key, so apps that never impersonate are unaffected.

Configure the TTL in your `.env`:

```env
IMPERSONATE_TTL_SECONDS=3600
```

To register it yourself instead, opt out in `config/kolydart.php` and add the **package** class to your kernel:

```php
'impersonate' => [
    'auto_register_timeout' => false,
],
```

```php
protected $middlewareGroups = [
    'web' => [
        // ... existing middleware
        \Kolydart\Laravel\App\Http\Middleware\EnforceImpersonationTimeout::class,
    ],
];
```

> **Do not use `vendor:publish --tag=middleware` for this.** That tag copies the middleware files verbatim, so the published copies still declare `namespace Kolydart\Laravel\App\Http\Middleware`. A kernel entry for `\App\Http\Middleware\EnforceImpersonationTimeout::class` therefore refers to a class that does not exist. Earlier versions of this document recommended exactly that, which meant `ttl_seconds` was documented but never enforced — if you followed those instructions, remove the `\App\Http\Middleware\` entry from your kernel.

---

