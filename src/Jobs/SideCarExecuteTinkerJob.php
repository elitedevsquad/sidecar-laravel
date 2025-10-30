<?php

namespace EliteDevSquad\SidecarLaravel\Jobs;

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

    public function __construct(
        public string $code
    ) {}

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            Log::debug('Sidecar Tinker job cancelled as batch was cancelled');

            return;
        }

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
