<?php

declare(strict_types=1);

namespace EliteDevSquad\SidecarLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SidecarInjectJsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (app()->isProduction()) {
            return $response; // @codeCoverageIgnore
        }

        if (! config('devsquad-sidecar.enabled', true)) {
            return $response;
        }

        if (! config('devsquad-sidecar.auto_inject_assets', true)) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type') ?? '';

        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || ! str_contains($content, '</body>')) {
            return $response;
        }

        $script = '<script src="/__devsquad-sidecar/assets/js" defer></script>';

        $response->setContent(str_replace('</body>', $script.'</body>', $content));

        return $response;
    }
}
