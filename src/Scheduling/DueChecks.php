<?php

namespace NetOs\Vitals\Scheduling;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Vitals;

final readonly class DueChecks
{
    public function __construct(private Vitals $vitals) {}

    /**
     * @return Collection<int, RegisteredCheck>
     */
    public function central(CarbonInterface $now): Collection
    {
        return $this->vitals->checks(Scope::Central)
            ->filter(fn (RegisteredCheck $check): bool => $check->frequency->isDue($now))
            ->values();
    }

    /**
     * @return Collection<int, RegisteredCheck>
     */
    public function tenant(TenantReference $tenant, CarbonInterface $now): Collection
    {
        return $this->applicable($tenant)
            ->filter(fn (RegisteredCheck $check): bool => $check->frequency->isDue($now, $tenant->key))
            ->values();
    }

    /**
     * @return Collection<int, RegisteredCheck>
     */
    public function applicable(TenantReference $tenant): Collection
    {
        return $this->vitals->checks(Scope::Tenant)
            ->filter(fn (RegisteredCheck $check): bool => $check->check->appliesTo($tenant))
            ->values();
    }
}
