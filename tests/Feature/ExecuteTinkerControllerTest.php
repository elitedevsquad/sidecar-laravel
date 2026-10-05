<?php

namespace Tests\Feature;

use EliteDevSquad\SidecarLaravel\{FakeClock, TinkerRunner};
use EliteDevSquad\SidecarLaravel\Http\Middleware\SidecarMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\{postJson, withoutMiddleware};

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow();
    withoutMiddleware(SidecarMiddleware::class);
});

it('handles exception when executing tinker code', function () {

    postJson('__devsquad-sidecar/execute-tinker', ['code' => base64_encode("throw new RuntimeException('oops');")])
        ->assertOk()
        ->assertJson([
            'output' => 'Error executing code: oops',
        ]);
});

it('change clock when clock input is provided', function () {
    $target = now()->addDays(2);

    FakeClock::set($target);

    postJson('__devsquad-sidecar/execute-tinker', [
        'code' => base64_encode('now()'),
    ])
        ->assertOk();

    expect(now()->timestamp)->toEqualWithDelta($target->timestamp, 2);
});

it('refuses tinker code when tinker is disabled', function (string $route) {
    config(['devsquad-sidecar.tinker_enabled' => false]);

    postJson("__devsquad-sidecar/{$route}", ['code' => base64_encode('1 + 1')])
        ->assertForbidden();
})->with(['execute-tinker', 'execute-tinker-on-queue']);

it('lists the variables the code assigns', function () {
    Carbon::setTestNow('2026-10-04 13:22:10');

    postJson('__devsquad-sidecar/execute-tinker', [
        'code' => base64_encode("\$name = 'Luan';\n\$date = now();\n\$tags = ['a' => 1];"),
    ])
        ->assertOk()
        ->assertJson([
            'output' => "\$name = 'Luan';\n\$date = '2026-10-04 13:22:10';\n\$tags = {\n    \"a\": 1\n};",
        ]);
});

it('shows the last value when nothing is assigned', function (string $code, string $output) {
    postJson('__devsquad-sidecar/execute-tinker', ['code' => base64_encode($code)])
        ->assertOk()
        ->assertJson(['output' => $output]);
})->with([
    'expression' => ['collect([1, 2])->sum()', '3'],
    'closure' => ["(function () { return 'done'; })()", "'done'"],
    'echo' => ["echo 'hi';", 'hi'],
    'null' => ['null', ''],
]);

it('falls back to the tinker command without PsySH', function () {
    app()->bind(TinkerRunner::class, fn () => new class() extends TinkerRunner
    {
        public static function supportsShell(): bool
        {
            return false;
        }
    });

    postJson('__devsquad-sidecar/execute-tinker', ['code' => base64_encode('echo 2;')])
        ->assertOk()
        ->assertJson(['output' => 'Result: 2']);
});

it('presents each kind of value', function (string $code, string $output) {
    postJson('__devsquad-sidecar/execute-tinker', ['code' => base64_encode('$value = '.$code.';')])
        ->assertOk()
        ->assertJson(['output' => '$value = '.$output.';']);
})->with([
    'null' => ['null', 'null'],
    'bool' => ['true', 'true'],
    'float' => ['1.5', '1.5'],
    'backed enum' => ['Tests\\Fixtures\\TinkerStatus::Active', "Tests\\Fixtures\\TinkerStatus::Active ('active')"],
    'unit enum' => ['Tests\\Fixtures\\TinkerSuit::Hearts', 'Tests\\Fixtures\\TinkerSuit::Hearts'],
    'collection' => ['collect([1])', "[\n    1\n]"],
    'stringable' => ["str('Luan')", "'Luan'"],
    'json serializable' => ['new class implements JsonSerializable { public function jsonSerialize(): mixed { return 1; } }', '1'],
    'object' => ['new ArrayIterator([])', 'ArrayIterator'],
    'unencodable' => ['[NAN]', 'array'],
    'resource' => ["fopen('php://memory', 'r')", 'resource (stream)'],
]);
