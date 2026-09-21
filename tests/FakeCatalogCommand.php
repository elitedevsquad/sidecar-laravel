<?php

namespace Tests;

use Illuminate\Console\Command;

class FakeCatalogCommand extends Command
{
    protected $signature = 'sidecar-test:probe {who : Who to greet} {--loud} {--times=1 : How many times} {--tag=* : Tags}';

    protected $description = 'Probe used by the command catalog tests';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
