<?php

namespace EliteDevSquad\SidecarLaravel;

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputOption};
use Throwable;

class CommandCatalog
{
    /**
     * @var array<int, string>
     */
    private const INHERITED_OPTIONS = [
        'help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $commands = [];

        /** @var array<string, Command> $registered */
        $registered = Artisan::all();

        foreach ($registered as $name => $command) {
            if (! $this->isApplicationCommand($command) || $this->isBlocked($name, $command)) {
                continue;
            }

            $commands[] = $this->describe($name, $command);
        }

        usort($commands, function (array $a, array $b): int {
            /** @var string $left */
            $left = $a['name'];
            /** @var string $right */
            $right = $b['name'];

            return strcmp($left, $right);
        });

        return $commands;
    }

    public function isBlocked(string $name, ?Command $command = null): bool
    {
        /** @var array<int, string> $patterns */
        $patterns = config('devsquad-sidecar.blocked_commands', []);

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }

            if ($command !== null && Str::is($pattern, $command::class)) {
                return true;
            }
        }

        return false;
    }

    public function isExecutable(string $name): bool
    {
        /** @var array<string, Command> $registered */
        $registered = Artisan::all();

        return ! $this->isBlocked($name, $registered[$name] ?? null);
    }

    private function isApplicationCommand(Command $command): bool
    {
        if ($command->isHidden()) {
            return false;
        }

        if ($command instanceof ClosureCommand) {
            return true;
        }

        try {
            $file = (new ReflectionClass($command))->getFileName();
        } catch (Throwable) {
            return false;
        }

        if ($file === false) {
            return false;
        }

        $file = realpath($file) ?: $file;

        return ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(string $name, Command $command): array
    {
        $definition = $command->getDefinition();

        $options = array_filter(
            $definition->getOptions(),
            fn (InputOption $option) => ! in_array($option->getName(), self::INHERITED_OPTIONS, true)
        );

        return [
            'name' => $name,
            'description' => $command->getDescription(),
            'arguments' => array_values(array_map(
                fn (InputArgument $argument) => [
                    'name' => $argument->getName(),
                    'description' => $argument->getDescription(),
                    'required' => $argument->isRequired(),
                    'array' => $argument->isArray(),
                    'default' => $argument->getDefault(),
                ],
                $definition->getArguments()
            )),
            'options' => array_values(array_map(
                fn (InputOption $option) => [
                    'name' => $option->getName(),
                    'shortcut' => $option->getShortcut(),
                    'description' => $option->getDescription(),
                    'type' => $this->optionType($option),
                    'default' => $option->getDefault(),
                ],
                $options
            )),
        ];
    }

    private function optionType(InputOption $option): string
    {
        if (! $option->acceptValue()) {
            return 'boolean';
        }

        return $option->isArray() ? 'list' : 'value';
    }
}
