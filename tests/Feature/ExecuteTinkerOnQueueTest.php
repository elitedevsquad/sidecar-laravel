<?php

use EliteDevSquad\SidecarLaravel\Http\Jobs\SideCarExecuteTinkerJob;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\{Artisan, Bus, Log, Queue};

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

        $kernel = \Mockery::mock(Kernel::class)
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
            ->with('Sidecar Tinker executed', \Mockery::on(fn ($context) => is_array($context)
                && ($context['code'] ?? null) === '1 + 1'
                && ($context['output'] ?? null) === '2'
                && array_key_exists('batchId', $context)
            ))
            ->andReturnNull();

        (new SideCarExecuteTinkerJob($code))->handle();
    });

    it('reports exceptions when tinker execution fails', function () {
        $code = '1 + 1';
        $exception = new \RuntimeException('tinker failed');

        $kernel = \Mockery::mock(Kernel::class)
            ->shouldReceive('call')
            ->once()
            ->with('tinker', ['--execute' => $code])
            ->andThrow($exception)
            ->getMock();

        app()->instance(Kernel::class, $kernel);
        Artisan::swap($kernel);

        $handler = \Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with($exception);
        $handler->shouldReceive('render')->andReturnNull();
        app()->instance(ExceptionHandler::class, $handler);

        Log::shouldReceive('error')->never();

        (new SideCarExecuteTinkerJob($code))->handle();
    });

    it('dispatches job directly when batch disabled', function () {
        config()->set('devsquad-sidecar.tinker_use_batch', false);

        $payload = ['code' => 'echo "hello";'];

        postJson('/__devsquad-sidecar/execute-tinker-on-queue', $payload)
            ->assertOk()
            ->assertExactJson(['output' => 'Job dispatched']);
    });
});
