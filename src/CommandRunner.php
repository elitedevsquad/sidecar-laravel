<?php

namespace EliteDevSquad\SidecarLaravel;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\{PhpExecutableFinder, Process};

class CommandRunner
{
    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function run(string $name, array $parameters = []): string
    {
        $process = $this->process($name, $this->toArguments($parameters));

        if ($process === null) {
            return 'Could not find the PHP executable to run the command.';
        }

        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        if ($output === '') {
            return $process->isSuccessful()
                ? 'Command executed successfully - '.$name
                : 'Command failed with exit code '.$process->getExitCode();
        }

        return $output;
    }

    public function capture(string $name): ?string
    {
        $process = $this->process($name);

        if ($process === null) {
            return null;
        }

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return null;
        }

        return $process->isSuccessful() ? $process->getOutput() : null;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function process(string $name, array $arguments = []): ?Process
    {
        $php = (new PhpExecutableFinder())->find(false);

        if ($php === false) {
            return null;
        }

        return new Process(
            [$php, base_path('artisan'), $name, ...$arguments, '--no-interaction'],
            base_path(),
            null,
            null,
            $this->timeout()
        );
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
