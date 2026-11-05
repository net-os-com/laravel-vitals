<?php

namespace NetOs\Vitals\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Running\ProcessCheck;
use NetOs\Vitals\Vitals;

/**
 * Runs one central check.
 */
final class RunCheckJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function __construct(public readonly string $checkKey)
    {
        $connection = config('vitals.queue_connection');
        $queue = config('vitals.queue');

        $this->onConnection(is_string($connection) && $connection !== '' ? $connection : null);
        $this->onQueue(is_string($queue) && $queue !== '' ? $queue : null);
    }

    public function uniqueId(): string
    {
        return 'central:'.$this->checkKey;
    }

    public function handle(Vitals $vitals, ProcessCheck $processCheck): void
    {
        $check = $vitals->find(Scope::Central, $this->checkKey);

        if ($check !== null) {
            $processCheck->handle($check);
        }
    }
}
