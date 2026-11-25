<?php

use EliteDevSquad\SidecarLaravel\Http\Controllers\GetSidecarDataController;
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
        ->toMatch('#<script src="/__devsquad-sidecar/assets/js\?v=\d+" defer>#')
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

it('embeds the data payload so the page needs no request to /data', function () {
    Config::set('app.name', 'Demo </script><b>');
    Config::set('devsquad-sidecar.branch_name', '');
    app()->setBasePath($dir = sys_get_temp_dir().'/sidecar-head-'.uniqid());
    mkdir($dir.'/.git', recursive: true);
    file_put_contents($dir.'/.git/HEAD', "ref: refs/heads/feature/inline\n");

    $content = (string) runMiddleware(makeHtmlResponse())->getContent();

    expect($content)->toContain('<script type="application/json" id="sidecar-data">')
        ->and(substr_count($content, '</script>'))->toBe(2);

    preg_match('#id="sidecar-data">(.*?)</script>#', $content, $match);
    $data = json_decode($match[1], true);

    expect($data)
        ->project_name->toBe('Demo </script><b>')
        ->branch->toBe('feature/inline')
        ->users->toBe([]);
});

it('still injects the script when the payload fails', function () {
    app()->bind(GetSidecarDataController::class, fn () => throw new RuntimeException('boom'));

    $content = (string) runMiddleware(makeHtmlResponse())->getContent();

    expect($content)->not->toContain('sidecar-data')
        ->toContain('/__devsquad-sidecar/assets/js');
});
