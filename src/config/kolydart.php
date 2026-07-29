<?php

return [
    'impersonate' => [
        'enabled'        => env('IMPERSONATE_ENABLED', false),
        'admin_role_id'  => 1,
        'session_key'    => 'impersonating_admin_id',
        'ttl_seconds'    => env('IMPERSONATE_TTL_SECONDS', 3600),
        'user_id_env'    => 'IMPERSONATE_USER_ID',
        'user_id'        => env('IMPERSONATE_USER_ID'),

        /**
         * Route name to redirect to once impersonation starts.
         * null => auto: 'frontend.home' when the impersonated user has no
         * backend access, 'admin.home' otherwise.
         */
        'redirect_to'      => null,

        /** Route name to redirect to once impersonation ends. */
        'redirect_back_to' => 'admin.users.index',

        'routes' => [
            'middleware' => ['web', 'auth', '2fa', 'backend'],

            /**
             * Middleware for the leave-impersonation route. The *impersonated*
             * user must be able to reach it, so it usually must not require
             * backend access. null => same stack as 'middleware'.
             */
            'leave_middleware' => null,

            'prefix'     => 'admin',
            'name'       => 'admin.',
        ],
    ],
];
