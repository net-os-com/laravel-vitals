<?php

namespace NetOs\Vitals\Running;

use NetOs\Vitals\Exceptions\TenantNotInitialized;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Support\SentryEvents;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tenancy\TenantReference;

/**
 * Initializes the tenant once, runs its checks and always ends tenancy. When the tenant cannot be
 * initialized, every check is recorded as crashed.
 */
final readonly class ProcessTenantChecks
{
    public function __construct(
        private TenancyAdapter $tenancy,
        private ProcessCheck $processCheck,
    ) {}

    /**
     * @param  iterable<RegisteredCheck>  $checks
     * @return array<string, CheckRun> keyed by check key
     */
    public function handle(TenantReference $tenant, iterable $checks): array
    {
        $checks = is_array($checks) ? $checks : iterator_to_array($checks, false);

        if ($checks === []) {
            return [];
        }

        try {
            return $this->tenancy->run($tenant, function () use ($tenant, $checks): array {
                $runs = [];

                foreach ($checks as $check) {
                    $runs[$check->key()] = $this->processCheck->handle($check, $tenant);
                }

                return $runs;
            });
        } catch (TenantNotInitialized $exception) {
            report($exception);
            $meta = SentryEvents::meta();

            $runs = [];

            foreach ($checks as $check) {
                $runs[$check->key()] = $this->processCheck->crash($check, $tenant, $exception->getMessage(), $meta);
            }

            return $runs;
        }
    }
}
