<?php

namespace NetOs\Vitals\Running;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use NetOs\Vitals\Events\CheckFinished;
use NetOs\Vitals\Events\CheckStatusChanged;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Result;
use NetOs\Vitals\Storage\ResultRepository;
use NetOs\Vitals\Tenancy\TenantReference;

/**
 * Runs a check, stores the outcome and dispatches the events.
 */
final readonly class ProcessCheck
{
    public function __construct(
        private CheckRunner $runner,
        private ResultRepository $results,
        private Dispatcher $events,
        private Mutes $mutes,
    ) {}

    public function handle(RegisteredCheck $check, ?TenantReference $tenant = null): CheckRun
    {
        return $this->record($check, $tenant, $this->runner->run($check->check));
    }

    public function record(RegisteredCheck $check, ?TenantReference $tenant, CheckRun $run): CheckRun
    {
        $previous = $this->results->record($check->key(), $tenant?->key, $run);

        if ($run->result->status->isHealthy()) {
            $this->mutes->releaseRecovered($check, $tenant);
        }

        $muted = $this->mutes->isMuted($check, $tenant, $run->finishedAt);

        $this->events->dispatch(new CheckFinished($check, $tenant, $run, $muted));

        if ($previous !== $run->result->status) {
            $this->events->dispatch(new CheckStatusChanged($check, $tenant, $previous, $run->result->status, $run, $muted));
        }

        return $run;
    }

    /**
     * @param  array<string, scalar|null>  $meta
     */
    public function crash(RegisteredCheck $check, ?TenantReference $tenant, string $summary, array $meta = []): CheckRun
    {
        $now = CarbonImmutable::now();

        return $this->record($check, $tenant, new CheckRun(Result::crashed($summary)->meta($meta), $now, $now, 0));
    }
}
