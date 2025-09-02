<?php

namespace Tests\Feature;

use EliteDevSquad\Sidecar\Http\Middleware\SidecarMiddleware;

use function Pest\Laravel\{postJson, withoutMiddleware};

beforeEach(function () {
    withoutMiddleware(SidecarMiddleware::class);
});

it('executes a valid artisan command', function () {
    postJson('__devsquad-sidecar/execute-command', ['command' => ['command' => 'view:clear', 'name' => 'Clear Compiled Views']])
        ->assertOk()
        ->assertContent('{"output":"\n   INFO  Compiled views cleared successfully.  \n\n"}');
});

it('handles exception when executing tinker code', function () {

    postJson('__devsquad-sidecar/execute-tinker', ['code' => base64_encode('bad')])
        ->assertOk()
        ->assertJson([
            'output' => 'Error executing code: oops',
        ]);
});
