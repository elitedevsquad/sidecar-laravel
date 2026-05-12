<?php

use function Pest\Laravel\getJson;

it('serves the sidecar javascript file', function () {
    getJson('/__devsquad-sidecar/assets/js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript');
});

it('returns the built javascript bundle', function () {
    $response = getJson('/__devsquad-sidecar/assets/js');

    expect($response->getContent())
        ->toContain('__devsquad-sidecar')
        ->toContain('DOMContentLoaded');
});

it('auto-instantiates sidecar on DOMContentLoaded', function () {
    $response = getJson('/__devsquad-sidecar/assets/js');

    expect($response->getContent())->toContain('DOMContentLoaded');
});
