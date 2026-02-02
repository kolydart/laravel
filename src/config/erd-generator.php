<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | The default path where the generated Mermaid ERD file will be saved.
    |
    */
    'output' => 'docs/database-erd.md',

    /*
    |--------------------------------------------------------------------------
    | Simplify Relationships
    |--------------------------------------------------------------------------
    |
    | If set to true, junction tables (tables with only 2 foreign keys) will
    | be hidden and replaced with a direct Many-to-Many relationship line.
    |
    */
    'simplify_relationships' => true,

    /*
    |--------------------------------------------------------------------------
    | Font Size
    |--------------------------------------------------------------------------
    |
    | CSS Font size directive for the diagram (e.g., '20px').
    | Leave null to use the default Mermaid font size.
    |
    */
    'font_size' => "24px",

    /*
    |--------------------------------------------------------------------------
    | Ignored Tables
    |--------------------------------------------------------------------------
    |
    | List of specific tables to exclude from the diagram.
    | These are added to the built-in system tables list.
    |
    */
    'ignored_tables' => [
        'audit_logs',
        'media',
        'migrations',
        'password_resets',
        'permissions',
        'personal_access_tokens',
        'roles',
        'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Columns
    |--------------------------------------------------------------------------
    |
    | List of specific columns to exclude from all tables.
    |
    */
    'ignored_columns' => [
        'created_at',
        'updated_at',
        // 'deleted_at',
    ],
];
