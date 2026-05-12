<?php

use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarInjectJsMiddleware;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Config;

function makeHtmlResponse(string $body = '<html><body><h1>Hello</h1></body></html>'): Response
{
    return response($body)->header('Content-Type', 'text/html; charset=UTF-8');
}

function runMiddleware(Response $response): Response
{
    $request = Request::create('/');
    $middleware = new SidecarInjectJsMiddleware();

    /** @var Response $result */
    $result = $middleware->handle($request, fn () => $response);

    return $result;
}

beforeEach(function () {
    Config::set('devsquad-sidecar.enabled', true);
    Config::set('devsquad-sidecar.auto_inject_assets', true);
});

it('injects the sidecar script tag with src before </body>', function () {
    $response = runMiddleware(makeHtmlResponse());

    expect($response->getContent())
        ->toContain('<script src="/__devsquad-sidecar/assets/js"')
        ->toContain('</script></body>');
});

it('does not inject when sidecar is disabled', function () {
    Config::set('devsquad-sidecar.enabled', false);

    $response = runMiddleware(makeHtmlResponse());

    expect($response->getContent())->not->toContain('/__devsquad-sidecar/assets/js');
});

it('does not inject when auto_inject_assets is disabled', function () {
    Config::set('devsquad-sidecar.auto_inject_assets', false);

    $response = runMiddleware(makeHtmlResponse());

    expect($response->getContent())->not->toContain('/__devsquad-sidecar/assets/js');
});

it('does not inject on json responses', function () {
    $jsonResponse = response()->json(['foo' => 'bar']);
    $request = Request::create('/');
    $middleware = new SidecarInjectJsMiddleware();

    /** @var Response $result */
    $result = $middleware->handle($request, fn () => $jsonResponse);

    expect($result->getContent())->not->toContain('/__devsquad-sidecar/assets/js');
});

it('does not inject when response has no </body> tag', function () {
    $response = runMiddleware(makeHtmlResponse('<div>partial content</div>'));

    expect($response->getContent())->not->toContain('/__devsquad-sidecar/assets/js');
});

it('injects script only once even with multiple </body> occurrences', function () {
    $html = '<html><body><p>Hi</p></body></html>';
    $response = runMiddleware(makeHtmlResponse($html));
    $content = $response->getContent();

    expect(substr_count((string) $content, '/__devsquad-sidecar/assets/js'))->toBe(1);
});
