<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Http\Requests\ExecuteCommandRequest;
use EliteDevSquad\SidecarLaravel\Traits\WithFakeClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

readonly class ExecuteCommandController
{
    use WithFakeClock;

    public function __invoke(ExecuteCommandRequest $request): JsonResponse
    {
        /**
         * @var array{
         *     command: string,
         * } $data
         */
        $data = $request->validated();

        $this->setFakeClock();

        try {
            /** @var string $command */
            $command = $data['command'];

            Artisan::call($command);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing command: '.$e->getMessage();
        }

        return response()->json(['output' => $output]);
    }
}
