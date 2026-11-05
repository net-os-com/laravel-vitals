<?php

namespace NetOs\Vitals\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use NetOs\Vitals\Scheduling\Dispatcher;

final class DispatchCommand extends Command
{
    protected $signature = 'vitals:dispatch {--all : Dispatch every check, not only the ones that are due}';

    protected $description = 'Put the checks that are due on the queue';

    public function handle(Dispatcher $dispatcher): int
    {
        $jobs = $this->option('all')
            ? $dispatcher->dispatchAll()
            : $dispatcher->dispatchDue(CarbonImmutable::now());

        $this->components->info("Dispatched {$jobs} ".str('job')->plural($jobs).'.');

        return self::SUCCESS;
    }
}
