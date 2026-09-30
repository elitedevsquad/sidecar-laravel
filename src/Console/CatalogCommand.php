<?php

namespace EliteDevSquad\SidecarLaravel\Console;

use EliteDevSquad\SidecarLaravel\CommandCatalog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogCommand extends Command
{
    protected $signature = 'sidecar:catalog';

    protected $description = 'Print the commands the Sidecar panel lists, as JSON';

    protected $hidden = true;

    public function handle(CommandCatalog $catalog): int
    {
        $this->output->writeln(
            (string) json_encode($catalog->all(), JSON_INVALID_UTF8_SUBSTITUTE),
            OutputInterface::OUTPUT_RAW
        );

        return self::SUCCESS;
    }
}
