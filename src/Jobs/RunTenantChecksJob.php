<?php

namespace NetOs\Vitals\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Running\ProcessTenantChecks;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Vitals;

/**
 * Runs the given checks for one tenant, inside a single tenancy initialization.
 */
final class RunTenantChecksJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 900;

    /**
     * @param  array<int, string>  $checkKeys
     */
    public function __construct(
        public readonly string $tenantKey,
        public readonly array $checkKeys,
    ) {
        $connection = config('vitals.queue_connection');
        $queue = config('vitals.queue');

        $this->onConnection(is_string($connection) && $connection !== '' ? $connection : null);
        $this->onQueue(is_string($queue) && $queue !== '' ? $queue : null);
    }

    public function uniqueId(): string
    {
        return 'tenant:'.$this->tenantKey.':'.implode(',', $this->checkKeys);
    }

    public function handle(Vitals $vitals, TenancyAdapter $tenancy, ProcessTenantChecks $processTenantChecks): void
    {
        $tenant = $tenancy->find($this->tenantKey);

        if ($tenant === null) {
            return;
        }

        $checks = collect($this->checkKeys)
            ->map(fn (string $key): ?RegisteredCheck => $vitals->find(Scope::Tenant, $key))
            ->filter(fn (?RegisteredCheck $check): bool => $check !== null && $check->check->appliesTo($tenant))
            ->values()
            ->all();

        $processTenantChecks->handle($tenant, $checks);
    }
}
