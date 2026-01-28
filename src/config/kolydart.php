<?php

return [
    'impersonate' => [
        'enabled' => env('IMPERSONATE_ENABLED', false),
        'admin_role_id' => 1,
        'user_id_env' => 'IMPERSONATE_USER_ID',
    ],
];
