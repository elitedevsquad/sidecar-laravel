<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Http\Jobs\SideCarExecuteTinkerJob;
use EliteDevSquad\SidecarLaravel\Http\Requests\ExecuteTinkerRequest;
use EliteDevSquad\SidecarLaravel\Traits\WithFakeClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

readonly class ExecuteTinkerOnQueueController
{
    use WithFakeClock;

    public function __invoke(ExecuteTinkerRequest $request): JsonResponse
    {
        /**
         * @var array{
         *     code: string
         * } $data
         */
        $data = $request->validated();

        $this->setFakeClock();

        $batch = Bus::batch([
            new SideCarExecuteTinkerJob($data['code']),
        ])->allowFailures()
            ->dispatch();

        return response()->json(['output' => 'Batch ID: '.$batch->id]);
    }
}
