<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\{CommandCatalog, CommandRunner};
use EliteDevSquad\SidecarLaravel\Http\Requests\ExecuteCommandRequest;
use EliteDevSquad\SidecarLaravel\Traits\WithFakeClock;
use Illuminate\Http\JsonResponse;
use Throwable;

readonly class ExecuteCommandController
{
    use WithFakeClock;

    public function __construct(
        private CommandCatalog $catalog,
        private CommandRunner $runner,
    ) {}

    public function __invoke(ExecuteCommandRequest $request): JsonResponse
    {
        /**
         * @var array{
         *     command: string,
         *     parameters?: array<string, mixed>,
         * } $data
         */
        $data = $request->validated();

        /** @var string $command */
        $command = $data['command'];

        /** @var array<string, mixed> $parameters */
        $parameters = $data['parameters'] ?? [];

        [$name, $parameters] = $parameters === []
            ? $this->split($command)
            : [$command, $parameters];

        if (! $this->catalog->isExecutable($name)) {
            return response()->json(['output' => 'This command is blocked by the Sidecar configuration.'], 403);
        }

        $this->setFakeClock();

        try {
            $output = $this->runner->run($name, $parameters);
        } catch (Throwable $e) {
            $output = 'Error executing command: '.$e->getMessage();
        }

        return response()->json(['output' => $output]);
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function split(string $command): array
    {
        $parts = preg_split('/\s+/', trim($command)) ?: [];
        $name = array_shift($parts) ?? '';

        return [$name, $parts];
    }
}
