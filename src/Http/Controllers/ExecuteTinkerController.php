<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Http\Requests\ExecuteTinkerRequest;
use EliteDevSquad\SidecarLaravel\TinkerRunner;
use EliteDevSquad\SidecarLaravel\Traits\WithFakeClock;
use Illuminate\Http\JsonResponse;

readonly class ExecuteTinkerController
{
    use WithFakeClock;

    public function __invoke(ExecuteTinkerRequest $request, TinkerRunner $runner): JsonResponse
    {
        /**
         * @var array{
         *     code: string
         * } $data
         */
        $data = $request->validated();

        $this->setFakeClock();

        if (! defined('STDIN')) {
            define('STDIN', fopen('php://stdin', 'r'));
        }

        set_time_limit(config()->integer('devsquad-sidecar.tinker_timeout', 60));

        $output = $runner->run($data['code']);

        return response()->json(['output' => $output]);
    }
}
