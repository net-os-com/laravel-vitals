<?php

namespace NetOs\Vitals\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Models\CheckState;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Storage\ResultRepository;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Vitals;

/**
 * Combines the registered checks with their stored states into what the dashboard shows.
 */
final readonly class Snapshots
{
    private const int STRIP_LENGTH = 24;

    public function __construct(
        private Vitals $vitals,
        private ResultRepository $results,
        private TenancyAdapter $tenancy,
        private Mutes $mutes,
    ) {}

    /**
     * @return Collection<int, CheckSnapshot>
     */
    public function central(CarbonImmutable $now): Collection
    {
        $states = $this->keyed($this->results->states());
        $recent = $this->results->recentStatuses(null, self::STRIP_LENGTH);
        $mutes = $this->mutes->active($now);

        return $this->vitals->checks(Scope::Central)->map(fn (RegisteredCheck $check): CheckSnapshot => CheckSnapshot::make(
            $check,
            null,
            $states->get($check->key()),
            $now,
            $recent->get($check->key().'|'),
            $this->mutes->covering($mutes, $check, null),
        ));
    }

    public function tenant(TenantReference $tenant, CarbonImmutable $now, bool $withRecent = true): TenantSnapshot
    {
        $states = $this->keyed($this->results->states($tenant->key));
        $recent = $withRecent ? $this->results->recentStatuses($tenant->key, self::STRIP_LENGTH) : new Collection;
        $mutes = $this->mutes->active($now);

        return new TenantSnapshot($tenant, $this->vitals->checks(Scope::Tenant)->map(
            fn (RegisteredCheck $check): CheckSnapshot => CheckSnapshot::make(
                $check,
                $tenant,
                $states->get($check->key()),
                $now,
                $recent->get($check->key().'|'.$tenant->key),
                $this->mutes->covering($mutes, $check, $tenant->key),
            ),
        ));
    }

    /**
     * @return Collection<int, TenantSnapshot>
     */
    public function tenants(CarbonImmutable $now): Collection
    {
        $states = $this->results->states(allTenants: true)
            ->groupBy(fn (CheckState $state): string => (string) $state->tenant_key)
            ->map(fn (Collection $states): Collection => $this->keyed($states));

        $checks = $this->vitals->checks(Scope::Tenant);
        $mutes = $this->mutes->active($now);

        return $this->tenancy->tenants()->map(fn (TenantReference $tenant): TenantSnapshot => new TenantSnapshot(
            $tenant,
            $checks->map(fn (RegisteredCheck $check): CheckSnapshot => CheckSnapshot::make(
                $check,
                $tenant,
                $states->get($tenant->key)?->get($check->key()),
                $now,
                null,
                $this->mutes->covering($mutes, $check, $tenant->key),
            )),
        ));
    }

    public function findTenant(string $key): ?TenantReference
    {
        return $this->tenancy->find($key);
    }

    /**
     * @param  Collection<int, CheckState>  $states
     * @return Collection<string, CheckState>
     */
    private function keyed(Collection $states): Collection
    {
        return $states->keyBy(fn (CheckState $state): string => $state->check_key);
    }
}
