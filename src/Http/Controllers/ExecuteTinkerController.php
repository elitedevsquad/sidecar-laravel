<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Http\Requests\ExecuteTinkerRequest;
use EliteDevSquad\SidecarLaravel\Traits\WithFakeClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

readonly class ExecuteTinkerController
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

        try {
            Artisan::call('tinker', ['--execute' => $data['code']]);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $output = 'Error executing code: '.$e->getMessage();
        }

        $output = str($output)
            ->after('for this Tinker session.')
            ->trim()
            ->toString();

        return response()->json(['output' => $output]);
    }
}
