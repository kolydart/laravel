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

    /**
     * Serving media that does not sit on a web-served disk.
     *
     * spatie/laravel-medialibrary writes to the `public` disk by default, which
     * is under the document root: those files answer a URL without PHP running,
     * so no gate is ever consulted. Moving a collection to a private disk is
     * only half the job — this section is the other half, the route that serves
     * it back with an authorization check in front.
     *
     * The route reads files off the filesystem, so the private disk must use the
     * `local` driver — just rooted outside the document root. Media on a cloud
     * disk is protected by that bucket's own ACL and a temporary URL instead.
     *
     * See src/docs/private-media.md for the full procedure, including the order
     * the steps must be taken in and the filesystem-permission trap at the end.
     */
    'media' => [
        /** Register the protected media routes. Off by default. */
        'enabled' => false,

        'routes' => [
            'middleware' => ['web', 'auth'],
            'prefix'     => 'admin',
            'name'       => 'admin.',
        ],

        /**
         * Conversion names the route will serve. The value reaches a filesystem
         * path, so it is constrained here rather than checked in the controller.
         */
        'conversions' => ['thumb', 'preview'],

        /**
         * Collections that stay on a web-served disk and keep their /storage
         * URLs. Anything not listed here is addressed through the protected
         * route, whatever disk it currently sits on.
         *
         * Entries take either form, as `gates` does: 'ck-media' covers that
         * collection on every model, 'App\Listing@image' covers one model's.
         *
         * Listing a collection whose files have since moved to a private disk
         * does not resurrect the /storage URL: the disk has the last word, and
         * such media falls back to the protected route with a logged warning.
         */
        'public_collections' => [],

        /**
         * model_type (optionally `Model@collection`) => gate ability.
         *
         * `null` as the ability admits any authenticated user. A model that is
         * not listed, with no '*' fallback, is denied: a collection added later
         * is private until someone says otherwise.
         *
         * The ability is checked *without* the owning record, so it expresses a
         * class-level permission ("may this user view customers?"). If the
         * application's own listings are scoped — multi-tenant, team-owned,
         * explicitly granted — a gate name cannot say so and 'access' below is
         * the place to express it.
         */
        'gates' => [],

        /**
         * The class that answers "may this user read this file?". Replace it to
         * express per-record rules (an explicit grant, an embargo date, access
         * inherited from a parent record) that a gate name cannot carry.
         *
         * Must implement \Kolydart\Laravel\App\Support\MediaAccessContract,
         * which is the single method `allows(Media $media): bool`.
         */
        'access' => \Kolydart\Laravel\App\Support\MediaAccess::class,
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
