<?php

namespace EliteDevSquad\SidecarLaravel\Jobs;

use EliteDevSquad\SidecarLaravel\FakeClock;
use Illuminate\Bus\{Batchable, Queueable};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Artisan, Log};
use Throwable;

class SideCarExecuteTinkerJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public function __construct(
        public string $code
    ) {
        $this->timeout = config()->integer('devsquad-sidecar.tinker_timeout', 60);
    }

    public function handle(): void
    {
        if (app()->isProduction()) {
            return; // @codeCoverageIgnore
        }

        if ($this->batch() && $this->batch()->cancelled()) {
            Log::debug('Sidecar Tinker job cancelled as batch was cancelled');

            return;
        }

        FakeClock::refreshFromCache();

        try {
            Artisan::call('tinker', ['--execute' => $this->code]);

            $output = Artisan::output();

            Log::info('Sidecar Tinker executed', [
                'batchId' => $this->batch()->id ?? 'N/A',
                'output' => $output,
            ]);
        } catch (Throwable $e) {
            $this->fail($e);

            report($e);
        }
    }
}
