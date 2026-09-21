<?php

namespace EliteDevSquad\SidecarLaravel;

use Symfony\Component\Process\{PhpExecutableFinder, Process};

class CommandRunner
{
    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function run(string $name, array $parameters = []): string
    {
        $php = (new PhpExecutableFinder())->find(false);

        if ($php === false) {
            return 'Could not find the PHP executable to run the command.';
        }

        $process = new Process(
            [$php, base_path('artisan'), $name, ...$this->toArguments($parameters), '--no-interaction'],
            base_path(),
            null,
            null,
            $this->timeout()
        );

        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        if ($output === '') {
            return $process->isSuccessful()
                ? 'Command executed successfully - '.$name
                : 'Command failed with exit code '.$process->getExitCode();
        }

        return $output;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     * @return array<int, string>
     */
    private function toArguments(array $parameters): array
    {
        $arguments = [];

        foreach ($parameters as $key => $value) {
            $isOption = str_starts_with((string) $key, '--');

            if (! $isOption) {
                $arguments[] = $this->stringify($value);

                continue;
            }

            if ($value === true) {
                $arguments[] = (string) $key;

                continue;
            }

            if ($value === false || $value === null || $value === '') {
                continue;
            }

            foreach ((array) $value as $item) {
                $arguments[] = $key.'='.$this->stringify($item);
            }
        }

        return $arguments;
    }

    private function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function timeout(): float
    {
        /** @var int|float $timeout */
        $timeout = config('devsquad-sidecar.command_timeout', 120);

        return (float) $timeout;
    }
}
