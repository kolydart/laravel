<?php

return [
    'impersonate' => [
        'enabled'        => env('IMPERSONATE_ENABLED', false),
        'admin_role_id'  => 1,
        'session_key'    => 'impersonating_admin_id',
        'ttl_seconds'    => env('IMPERSONATE_TTL_SECONDS', 3600),
        'user_id_env'    => 'IMPERSONATE_USER_ID',
        'user_id'        => env('IMPERSONATE_USER_ID'),
        'routes' => [
            'middleware' => ['web', 'auth', '2fa', 'backend'],
            'prefix'     => 'admin',
            'name'       => 'admin.',
        ],
    ],
];
