<?php

namespace NetOs\Vitals\Scheduling;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Cache\Repository as Cache;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Jobs\RunCheckJob;
use NetOs\Vitals\Jobs\RunTenantChecksJob;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Vitals;

/**
 * Puts checks on the queue: one job per central check, one job per tenant.
 */
final readonly class Dispatcher
{
    public const string HEARTBEAT_KEY = 'vitals:dispatcher-heartbeat';

    public function __construct(
        private Vitals $vitals,
        private DueChecks $dueChecks,
        private TenancyAdapter $tenancy,
        private Bus $bus,
        private Cache $cache,
    ) {}

    /**
     * Dispatch the checks that are due now and record the heartbeat.
     *
     * @return int the number of dispatched jobs
     */
    public function dispatchDue(CarbonInterface $now): int
    {
        $this->cache->forever(self::HEARTBEAT_KEY, $now->getTimestamp());

        $jobs = $this->dispatchCentral($this->dueChecks->central($now));

        foreach ($this->tenancy->tenants() as $tenant) {
            $jobs += $this->dispatchTenant($tenant, $this->dueChecks->tenant($tenant, $now));
        }

        return $jobs;
    }

    /**
     * Dispatch every check, regardless of its schedule.
     */
    public function dispatchAll(): int
    {
        $jobs = $this->dispatchCentral($this->vitals->checks(Scope::Central));

        foreach ($this->tenancy->tenants() as $tenant) {
            $jobs += $this->dispatchTenant($tenant, $this->dueChecks->applicable($tenant));
        }

        return $jobs;
    }

    /**
     * @param  iterable<RegisteredCheck>  $checks
     */
    public function dispatchCentral(iterable $checks): int
    {
        $jobs = 0;

        foreach ($checks as $check) {
            $this->bus->dispatch(new RunCheckJob($check->key()));
            $jobs++;
        }

        return $jobs;
    }

    /**
     * @param  iterable<RegisteredCheck>  $checks
     */
    public function dispatchTenant(TenantReference $tenant, iterable $checks): int
    {
        $keys = [];

        foreach ($checks as $check) {
            $keys[] = $check->key();
        }

        if ($keys === []) {
            return 0;
        }

        $this->bus->dispatch(new RunTenantChecksJob($tenant->key, $keys));

        return 1;
    }

    public function lastHeartbeat(): ?CarbonImmutable
    {
        $timestamp = $this->cache->get(self::HEARTBEAT_KEY);

        return is_numeric($timestamp) ? CarbonImmutable::createFromTimestamp((int) $timestamp) : null;
    }

    public function isHeartbeatStale(CarbonInterface $now): bool
    {
        $heartbeat = $this->lastHeartbeat();

        return $heartbeat === null || $heartbeat->diffInSeconds($now) > 180;
    }
}
