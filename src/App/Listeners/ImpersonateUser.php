<?php

namespace Kolydart\Laravel\App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/**
 * User Impersonation Listener
 *
 * This listener allows an Administrator to automatically log in as another user
 * based on an environment variable. This is primarily for development and debugging.
 *
 * How it works:
 * 1. An Administrator (default Role ID 1) logs in successfully.
 * 2. The listener checks for a target User ID in the environment (default 'IMPERSONATE_USER_ID').
 * 3. If found, the session is immediately re-authenticated as that user.
 *
 * Configurable via .env:
 * - IMPERSONATE_USER_ID: The ID of the user to impersonate.
 * - IMPERSONATE_ENABLED: Set to false to disable this feature entirely.
 * - IMPERSONATE_ADMIN_ROLE_ID: The Role ID required to trigger impersonation (default 1).
 *
 * class is not tested, yet
 *
 */
class ImpersonateUser
{
    /**
     * Handle the event.
     *
     * @param  Login  $event
     * @return void
     */
    public function handle(Login $event): void
    {
        $config = config('kolydart.impersonate');

        if (!$config || !$config['enabled']) {
            return;
        }

        $userIdEnv = $config['user_id_env'] ?? 'USER_ID';
        $userId = env($userIdEnv);

        if ($userId && $event->user->roles()->where('id', $config['admin_role_id'])->exists()) {
            $targetUser = $event->user::find($userId);

            if ($targetUser) {
                Auth::login($targetUser);
            }
        }
    }
}
