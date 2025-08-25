<?php

return [
    'enabled' => env('DS_SIDECAR_ENABLED', true),

    'auth_token' => env('DS_SIDECAR_AUTH_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Commands enabled
    |--------------------------------------------------------------------------
    |
    | This option allows you to enable or disable the execution of Artisan
    | commands from the sidecar.
    |
    */
    'commands_enabled' => env('DS_SIDECAR_COMMANDS_ENABLED', true),

    /*
    |
    |--------------------------------------------------------------------------
    | Tinker enabled
    |--------------------------------------------------------------------------
    |
    | This option allows you to enable or disable the Tinker functionality
    | from the sidecar.
    |
    */
    'tinker_enabled' => env('DS_SIDECAR_TINKER_ENABLED', true),

    /*
    |
    |--------------------------------------------------------------------------
    | Fake Clock enabled
    |--------------------------------------------------------------------------
    |
    | This option allows you to enable or disable the Fake Clock functionality
    |
    */
    'fake_clock_enabled' => env('DS_SIDECAR_FAKE_CLOCK_ENABLED', true),

    /*
    |
    |--------------------------------------------------------------------------
    | Links
    |--------------------------------------------------------------------------
    |
    | This option allows you to define custom links that will be displayed
    | in the sidecar. Each link should have a 'name' and a 'url'.
    |
    */
    'links' => [
        [
            'name' => 'Admin',
            'url' => config('app.url').'/admin',
        ],
        [
            'name' => 'Mail',
            'url' => env('DS_SIDECAR_LINK_MAIL', ''),
        ],
        [
            'name' => 'Envoyer',
            'url' => env('DS_SIDECAR_LINK_ENVOYER', ''),
        ],
        [
            'name' => 'Horizon',
            'url' => config('app.url').'/horizon',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    | This option allows you to define custom Artisan commands that can be
    | executed from the sidecar.
    |
    */

    'commands' => [
        [
            'name' => 'Clear cached optimized files',
            'command' => 'optimize:clear',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Branch Name and URL
    |--------------------------------------------------------------------------
    |
    | These options allow you to specify the branch name and URL that will
    | be displayed in the sidecar.
    |
    */

    'branch_name' => env('HEADER_BRANCH_NAME', ''),

    'branch_url' => env('DS_SIDECAR_BRANCH_URL', ''),
];
