<?php

return [
    'audit_log' => [
        /**
         * Gate ability required to load the PgAuditLog grid's datasource.
         *
         * The component has no authorization of its own: rendered without a
         * :model parameter it exposes the whole audit_logs table — who did what,
         * to which record, from which IP. Authorization is normally handled by
         * the route that renders it; set this to an ability (e.g.
         * 'audit_log_access') for defence in depth.
         *
         * null => no check, preserving the behaviour of earlier versions.
         */
        'view_ability' => null,
    ],

    'impersonate' => [
        'enabled'        => env('IMPERSONATE_ENABLED', false),
        'admin_role_id'  => 1,
        'session_key'    => 'impersonating_admin_id',
        'ttl_seconds'    => env('IMPERSONATE_TTL_SECONDS', 3600),
        'user_id_env'    => 'IMPERSONATE_USER_ID',
        'user_id'        => env('IMPERSONATE_USER_ID'),

        /**
         * Append EnforceImpersonationTimeout to the 'web' middleware group
         * automatically, so 'ttl_seconds' is actually enforced. Set to false to
         * register the middleware yourself in app/Http/Kernel.php.
         */
        'auto_register_timeout' => true,

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
