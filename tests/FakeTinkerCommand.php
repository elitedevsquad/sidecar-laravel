<?php

namespace Tests;

use Illuminate\Console\Command;

class FakeTinkerCommand extends Command
{
    protected $signature = 'tinker {--execute=}';

    protected $description = 'Fake Tinker for testing';

    public function handle(): int
    {
        $code = $this->option('execute');

        if ($code === 'bad') {
            throw new \RuntimeException('oops');
        }

        if ($code === 'empty') {
            return self::SUCCESS;
        }

        $this->line('Booting Laravel... for this Tinker session. Result: 2');

        return self::SUCCESS;
    }
}
