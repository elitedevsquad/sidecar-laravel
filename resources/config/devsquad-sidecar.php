<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    */

    // Master switch. The routes are skipped in production regardless.
    'enabled' => env('DS_SIDECAR_ENABLED', true),

    // Inject the Sidecar script into HTML responses automatically.
    'auto_inject_assets' => env('DS_SIDECAR_AUTO_INJECT_ASSETS', true),

    // IPs allowed to execute anything. Empty means nobody, not everybody.
    'allowed_ips' => array_filter(array_map('trim', explode(',', env('DS_SIDECAR_ALLOWED_IPS', '127.0.0.1')))),

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'commands_enabled' => env('DS_SIDECAR_COMMANDS_ENABLED', true),

    'tinker_enabled' => env('DS_SIDECAR_TINKER_ENABLED', true),

    // Run queued Tinker snippets as a batch.
    'tinker_use_batch' => env('DS_SIDECAR_TINKER_USE_BATCH', true),

    'fake_clock_enabled' => env('DS_SIDECAR_FAKE_CLOCK_ENABLED', true),

    'health_enabled' => env('DS_SIDECAR_HEALTH_ENABLED', true),

    // How far back to count errors in the log.
    'health_log_window_hours' => env('DS_SIDECAR_HEALTH_LOG_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    | The panel lists the application's own Artisan commands, read from the
    | console kernel; framework and package commands are already excluded.
    |
    */

    'blocked_commands' => [
        // 'App\\Console\\Commands\\Deprecated\\*',
        // 'db:wipe',
    ],

    // Seconds a command may run. It runs as a subprocess, so the web server's
    // own request timeout does not apply.
    'command_timeout' => env('DS_SIDECAR_COMMAND_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Panel
    |--------------------------------------------------------------------------
    */

    // Shortcuts shown in the panel. Each needs a "name" and a "url".
    'links' => [
        [
            'name' => 'Admin',
            'url' => config('app.url').'/admin',
        ],
        [
            'name' => 'Horizon',
            'url' => config('app.url').'/horizon',
        ],
    ],

    'branch_name' => env('HEADER_BRANCH_NAME', ''),

    'branch_url' => env('DS_SIDECAR_BRANCH_URL', ''),

    /*
    | Badge drawn on the page itself, for browsers without the extension.
    | One of: "environment", "branch", "env_branch", "show_tag".
    */
    'badge_fallback' => env('DS_SIDECAR_BADGE_FALLBACK', 'branch'),
];
