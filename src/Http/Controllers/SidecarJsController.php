<?php

declare(strict_types=1);

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use Illuminate\Http\Response;

class SidecarJsController
{
    public function __invoke(): Response
    {
        $path = __DIR__.'/../../../dist/sidecar.js';

        /** @var string $content */
        $content = file_get_contents($path);

        return response($content)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
