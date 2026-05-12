<?php

return [
    'enabled' => env('DS_SIDECAR_ENABLED', true),

    // Automatically inject the Sidecar JS before </body> on every HTML response
    'auto_inject_assets' => env('DS_SIDECAR_AUTO_INJECT_ASSETS', true),

    // Allowed IPs to execute Sidecar features (comma-separated)
    'allowed_ips' => array_filter(array_map('trim', explode(',', env('DS_SIDECAR_ALLOWED_IPS', '127.0.0.1')))),

    // Enable or disable Artisan command execution
    'commands_enabled' => env('DS_SIDECAR_COMMANDS_ENABLED', true),

    // Enable or disable Tinker functionality
    'tinker_enabled' => env('DS_SIDECAR_TINKER_ENABLED', true),

    // Run Tinker queued jobs using batch mode
    'tinker_use_batch' => env('DS_SIDECAR_TINKER_USE_BATCH', true),

    // Enable or disable the Fake Clock feature
    'fake_clock_enabled' => env('DS_SIDECAR_FAKE_CLOCK_ENABLED', true),

    // Custom links displayed in the Sidecar panel
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

    // Custom Artisan commands available from the Sidecar
    'commands' => [
        [
            'name' => 'Clear cached optimized files',
            'command' => 'optimize:clear',
        ],
    ],

    // Git branch information displayed in the Sidecar
    'branch_name' => env('HEADER_BRANCH_NAME', ''),
    'branch_url' => env('DS_SIDECAR_BRANCH_URL', ''),
];
