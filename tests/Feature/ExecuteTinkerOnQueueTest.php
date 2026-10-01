<?php

use EliteDevSquad\SidecarLaravel\FakeClock;
use EliteDevSquad\SidecarLaravel\Jobs\SideCarExecuteTinkerJob;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Artisan, Bus, Cache, Log, Queue};

use function Pest\Laravel\{postJson, withoutMiddleware};

describe('Sidecar Tinker Execution', function () {
    beforeEach(function () {
        Bus::fake();
        Queue::fake();
        withoutMiddleware();
    });

    it('dispatches the job when hitting the tinker execution route', function () {
        $payload = ['code' => 'echo "hello";'];

        postJson('/__devsquad-sidecar/execute-tinker-on-queue', $payload)
            ->assertOk()
            ->assertJson(function ($json) {
                $json->where('output', fn ($value) => str_starts_with($value, 'Batch ID: '));
            });
    });

    it('executes tinker code and logs the output', function () {
        $code = '1 + 1';

        $kernel = Mockery::mock(Kernel::class)
            ->shouldReceive('call')
            ->once()
            ->with('tinker', ['--execute' => $code])
            ->andReturn(0)
            ->getMock()
            ->shouldReceive('output')
            ->once()
            ->andReturn('2')
            ->getMock();

        app()->instance(Kernel::class, $kernel);

        Artisan::swap($kernel);

        Log::shouldReceive('error')->zeroOrMoreTimes()->andReturnNull();

        Log::shouldReceive('info')
            ->zeroOrMoreTimes()
            ->with('Sidecar Tinker executed', Mockery::on(fn ($context) => is_array($context)
                && ($context['code'] ?? null) === '1 + 1'
                && ($context['output'] ?? null) === '2'
                && array_key_exists('batchId', $context)
            ))
            ->andReturnNull();

        (new SideCarExecuteTinkerJob($code))->handle();
    });

    it('reports exceptions when tinker execution fails', function () {
        $code = '1 + 1';
        $exception = new RuntimeException('tinker failed');

        $kernel = Mockery::mock(Kernel::class)
            ->shouldReceive('call')
            ->once()
            ->with('tinker', ['--execute' => $code])
            ->andThrow($exception)
            ->getMock();

        app()->instance(Kernel::class, $kernel);
        Artisan::swap($kernel);

        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with($exception);
        $handler->shouldReceive('render')->andReturnNull();
        app()->instance(ExceptionHandler::class, $handler);

        Log::shouldReceive('error')->never();

        (new SideCarExecuteTinkerJob($code))->handle();
    });

    it('runs with the fake clock the panel set last, not the one the worker started with', function () {
        $kernel = Mockery::mock(Kernel::class);
        $kernel->shouldReceive('call')->andReturn(0);
        $kernel->shouldReceive('output')->andReturn('');
        app()->instance(Kernel::class, $kernel);
        Artisan::swap($kernel);
        Log::shouldReceive('info')->zeroOrMoreTimes()->andReturnNull();

        Carbon::setTestNow(now()->addYears(5));
        Cache::forever(FakeClock::KEY, 86400);

        (new SideCarExecuteTinkerJob('now()'))->handle();
        expect(now()->timestamp)->toEqualWithDelta(time() + 86400, 2);

        Cache::forget(FakeClock::KEY);

        (new SideCarExecuteTinkerJob('now()'))->handle();
        expect(Carbon::hasTestNow())->toBeFalse();
    });

    it('takes its timeout from the config', function () {
        config()->set('devsquad-sidecar.tinker_timeout', 15);

        expect((new SideCarExecuteTinkerJob('1 + 1'))->timeout)->toBe(15);
    });

    it('dispatches job directly when batch disabled', function () {
        config()->set('devsquad-sidecar.tinker_use_batch', false);

        $payload = ['code' => 'echo "hello";'];

        postJson('/__devsquad-sidecar/execute-tinker-on-queue', $payload)
            ->assertOk()
            ->assertExactJson(['output' => 'Job dispatched']);
    });
});
