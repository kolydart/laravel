<?php

namespace Kolydart\Laravel\App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/**
 * User Impersonation Listener
 *
 * Allows an Administrator to automatically log in as another user based on an
 * environment variable. Primarily for development and debugging.
 *
 * How it works:
 * 1. An Administrator (default Role ID 1) logs in successfully.
 * 2. The listener checks for a target User ID in config (IMPERSONATE_USER_ID env).
 * 3. If found, the session is immediately re-authenticated as that user.
 *
 * The listener skips silently when a UI-based impersonation session is already
 * active, preventing non-deterministic auth swaps.
 *
 * Configurable via .env:
 * - IMPERSONATE_USER_ID: The ID of the user to impersonate.
 * - IMPERSONATE_ENABLED: Set to false to disable this feature entirely.
 * - IMPERSONATE_ADMIN_ROLE_ID: The Role ID required to trigger impersonation (default 1).
 */
class ImpersonateUser
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $config = config('kolydart.impersonate');

        if (!$config || !$config['enabled']) {
            return;
        }

        // Skip when a UI-based impersonation is already in progress
        if (session()->has(config('kolydart.impersonate.session_key', 'impersonating_admin_id'))) {
            return;
        }

        $userId = config('kolydart.impersonate.user_id');

        /** @var \Illuminate\Database\Eloquent\Model $eventUser */
        $eventUser = $event->user;

        if ($userId
            && method_exists($eventUser, 'roles')
            && $eventUser->roles()->where('id', $config['admin_role_id'])->exists()
        ) {
            $targetUser = $eventUser->newQuery()->find($userId);

            if ($targetUser) {
                Auth::login($targetUser);
            }
        }
    }
}
