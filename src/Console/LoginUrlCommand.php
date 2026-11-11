<?php

namespace EliteDevSquad\SidecarLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class LoginUrlCommand extends Command
{
    protected $signature = 'sidecar:login-url
        {user : The id of the user to log in as}
        {--base= : Host to put in front of the link, e.g. http://localhost:8082 (default: APP_URL)}
        {--redirect= : Same-host page to open after logging in}
        {--minutes=60 : How long the link stays valid}';

    protected $description = 'Print a signed link that logs a browser (or Playwright) in as the given user';

    public function handle(): int
    {
        $base = $this->option('base');
        $base = rtrim(is_string($base) && $base !== '' ? $base : config()->string('app.url'), '/');

        $path = URL::temporarySignedRoute(
            'devsquad-sidecar.login-as',
            now()->addMinutes((int) $this->option('minutes')),
            ['user' => $this->argument('user')],
            absolute: false,
        );

        $redirect = $this->option('redirect');

        if (is_string($redirect) && $redirect !== '') {
            $path .= '&redirect='.urlencode(str_starts_with($redirect, '/') ? $base.$redirect : $redirect);
        }

        $this->line($base.$path);

        return self::SUCCESS;
    }
}
