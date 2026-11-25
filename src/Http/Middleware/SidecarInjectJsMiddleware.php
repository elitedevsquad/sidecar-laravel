<?php

declare(strict_types=1);

namespace EliteDevSquad\SidecarLaravel\Http\Middleware;

use Closure;
use EliteDevSquad\SidecarLaravel\Http\Controllers\GetSidecarDataController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

        $version = @filemtime(__DIR__.'/../../../dist/sidecar.js') ?: '';

        $script = $this->dataScript().'<script src="/__devsquad-sidecar/assets/js?v='.$version.'" defer></script>';

        $response->setContent(str_replace('</body>', $script.'</body>', $content));

        return $response;
    }

    /**
     * The /data payload rides along with the page, so neither the page nor the extension has to ask for it.
     */
    private function dataScript(): string
    {
        try {
            $json = json_encode(
                app(GetSidecarDataController::class)->payload(),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (Throwable $e) {
            report($e);

            return '';
        }

        return $json === false ? '' : '<script type="application/json" id="sidecar-data">'.$json.'</script>';
    }
}
