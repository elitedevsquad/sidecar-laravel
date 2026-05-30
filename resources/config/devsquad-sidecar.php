<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sidecar Enabled
    |--------------------------------------------------------------------------
    |
    | This option determines whether Sidecar is enabled for the application.
    |
    */

    'enabled' => env('DS_SIDECAR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Inject Assets
    |--------------------------------------------------------------------------
    |
    | Automatically inject the Sidecar JS before </body> on every HTML response.
    |
    */

    'auto_inject_assets' => env('DS_SIDECAR_AUTO_INJECT_ASSETS', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed IPs
    |--------------------------------------------------------------------------
    |
    | The IP addresses that are allowed to access Sidecar features. Multiple
    | IPs can be specified as a comma-separated string in the environment.
    |
    */

    'allowed_ips' => array_filter(array_map('trim', explode(',', env('DS_SIDECAR_ALLOWED_IPS', '127.0.0.1')))),

    /*
    |--------------------------------------------------------------------------
    | Commands Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable Artisan command execution from the Sidecar panel.
    |
    */

    'commands_enabled' => env('DS_SIDECAR_COMMANDS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tinker Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable Tinker functionality from the Sidecar panel.
    |
    */

    'tinker_enabled' => env('DS_SIDECAR_TINKER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tinker Use Batch
    |--------------------------------------------------------------------------
    |
    | Run Tinker queued jobs using batch mode.
    |
    */

    'tinker_use_batch' => env('DS_SIDECAR_TINKER_USE_BATCH', true),

    /*
    |--------------------------------------------------------------------------
    | Fake Clock Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the Fake Clock feature from the Sidecar panel.
    |
    */

    'fake_clock_enabled' => env('DS_SIDECAR_FAKE_CLOCK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Custom Links
    |--------------------------------------------------------------------------
    |
    | Custom links displayed in the Sidecar panel. Each link should have a
    | "name" and "url" key.
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
    | Custom Commands
    |--------------------------------------------------------------------------
    |
    | Custom Artisan commands available from the Sidecar panel. Each command
    | should have a "name" and "command" key.
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
    | Git Branch
    |--------------------------------------------------------------------------
    |
    | Git branch information displayed in the Sidecar panel.
    |
    */

    'branch_name' => env('HEADER_BRANCH_NAME', ''),
    'branch_url' => env('DS_SIDECAR_BRANCH_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Badge Fallback
    |--------------------------------------------------------------------------
    |
    | Fallback badge for browsers without the Chrome extension (Safari/Mobile).
    |
    | Options:
    |   - "environment" : Shows the environment name (local, staging, sandbox)
    |   - "branch"      : Shows the current git branch name
    |   - "env_branch"  : Shows environment + branch (e.g., "local · main")
    |   - "show_tag"    : Shows the deploy tag on staging environments
    |
    */

    'badge_fallback' => env('DS_SIDECAR_BADGE_FALLBACK', 'branch'),
];
