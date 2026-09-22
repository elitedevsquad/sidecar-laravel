<?php

namespace Tests;

use EliteDevSquad\SidecarLaravel\CommandRunner;

class FakeCommandRunner extends CommandRunner
{
    public ?string $name = null;

    /** @var array<array-key, mixed> */
    public array $parameters = [];

    public string $output = 'fake output';

    public function run(string $name, array $parameters = []): string
    {
        $this->name = $name;
        $this->parameters = $parameters;

        return $this->output;
    }
}
