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

Route prefix, name prefix, and middleware are configurable via `config/kolydart.php`:

```php
'impersonate' => [
    'admin_role_id' => 1,              // Role ID that counts as "admin"
    'session_key'   => 'impersonating_admin_id',
    'ttl_seconds'   => env('IMPERSONATE_TTL_SECONDS', 3600),
    'routes' => [
        'middleware' => ['web', 'auth', '2fa', 'backend'],
        'prefix'     => 'admin',
        'name'       => 'admin.',
    ],
],
```

### Security

- **Admin-only**: the initiator must hold the `user_impersonate` Gate permission **and** belong to the configured `admin_role_id` — both checks are required.
- **No nested impersonation**: a second impersonate request while a session is already active returns HTTP 409.
- **No admin→admin escalation**: impersonating a user who is also an admin is blocked (HTTP 403).
- **No self-impersonation**: `abort_if($user->id === auth()->id(), 403)`.
- **Revoked-admin guard**: `leaveImpersonation` re-checks the admin's role before restoring the session — if the role was removed, the session is destroyed and the user is redirected to login.
- **TTL enforcement**: sessions expire after `ttl_seconds` (default: 3600 s). Register `EnforceImpersonationTimeout` in your HTTP kernel to enforce this on every request (see below).
- **Audit log**: every start/end event is written to `AuditLog` (if the model exists in `App\Models\AuditLog` or `App\AuditLog`).
- `leaveImpersonation` aborts with 403 if no session key exists (prevents direct URL access).
- Both routes require the configured middleware stack (default: `web`, `auth`, `2fa`, `backend`).

#### Registering the Timeout Middleware

Publish the middleware:

```bash
php artisan vendor:publish --tag=middleware
```

Then register it in `app/Http/Kernel.php` inside the `web` middleware group:

```php
protected $middlewareGroups = [
    'web' => [
        // ... existing middleware
        \App\Http\Middleware\EnforceImpersonationTimeout::class,
    ],
];
```

Configure the TTL in your `.env`:

```env
IMPERSONATE_TTL_SECONDS=3600
```

---

