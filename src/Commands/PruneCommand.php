<?php

namespace NetOs\Vitals\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use NetOs\Vitals\Storage\ResultRepository;

final class PruneCommand extends Command
{
    protected $signature = 'vitals:prune {--days= : Keep this many days instead of vitals.retention_days}';

    protected $description = 'Delete check results older than the retention period';

    public function handle(ResultRepository $results): int
    {
        $days = $this->option('days') ?? config('vitals.retention_days', 14);
        $days = is_numeric($days) ? (int) $days : 14;

        $deleted = $results->prune(CarbonImmutable::now()->subDays($days));

        $this->components->info("Deleted {$deleted} ".str('result')->plural($deleted)." older than {$days} days.");

        return self::SUCCESS;
    }
}
