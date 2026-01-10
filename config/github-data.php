<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub Data Repository Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration controls the GitHub-based data storage system.
    | Instead of using a traditional database, entities are stored as JSON
    | files in a GitHub repository, providing version control and easy editing.
    |
    */

    'owner' => env('GITHUB_DATA_OWNER', 'lordtoepel'),

    'repo' => env('GITHUB_DATA_REPO', 'leanscale-data'),

    'branch' => env('GITHUB_DATA_BRANCH', 'main'),

    'token' => env('GITHUB_DATA_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | How long to cache data from GitHub (in seconds).
    | Set to 0 to disable caching (not recommended for production).
    |
    */

    'cache_ttl' => env('GITHUB_DATA_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Entity Types
    |--------------------------------------------------------------------------
    |
    | Configuration for each entity type that can be stored in GitHub.
    | Each entity type specifies its directory path and whether it
    | is scoped to an organization.
    |
    */

    'entities' => [
        'organizations' => [
            'path' => 'organizations',
            'scoped' => false,
        ],
        'users' => [
            'path' => 'users',
            'scoped' => false,
        ],
        'members' => [
            'path' => 'members',
            'scoped' => true,
        ],
        'clients' => [
            'path' => 'clients',
            'scoped' => true,
        ],
        'projects' => [
            'path' => 'projects',
            'scoped' => true,
        ],
        'tasks' => [
            'path' => 'tasks',
            'scoped' => true,
        ],
        'timelogs' => [
            'path' => 'timelogs',
            'scoped' => true,
        ],
        'tags' => [
            'path' => 'tags',
            'scoped' => true,
        ],
        'project_members' => [
            'path' => 'project_members',
            'scoped' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for GitHub webhooks to enable real-time sync
    | when files are edited directly in GitHub.
    |
    */

    'webhook' => [
        'secret' => env('GITHUB_WEBHOOK_SECRET'),
        'enabled' => env('GITHUB_WEBHOOK_ENABLED', false),
    ],
];
