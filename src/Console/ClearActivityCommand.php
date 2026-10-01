<?php

namespace EliteDevSquad\SidecarLaravel\Console;

use EliteDevSquad\SidecarLaravel\Activity\ActivityLog;
use Illuminate\Console\Command;

class ClearActivityCommand extends Command
{
    protected $signature = 'sidecar:activity:clear
        {--days= : How many days of activity to keep for this run}
        {--all : Delete everything in storage/app/sidecar and start over}
        {--force : Do not ask before --all}
        {--dry-run : List what would be deleted}';

    protected $description = 'Delete old Sidecar activity logs and expired testers';

    public function handle(ActivityLog $log): int
    {
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if ($all && ! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('Delete all Sidecar activity and presence data?')) {
                $this->info('Nothing was deleted.');

                return self::SUCCESS;
            }
        }

        $days = $this->option('days');
        $result = $log->clear(is_numeric($days) ? (int) $days : null, $all, $dryRun);

        foreach ($result['paths'] as $path) {
            $this->line($path);
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
