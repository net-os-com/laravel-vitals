<?php

namespace NetOs\Vitals\Dashboard;

use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Models\CheckChange;
use NetOs\Vitals\Storage\ResultRepository;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Vitals;

/**
 * The latest status changes over all checks and tenants, for the overview.
 */
final readonly class RecentChanges
{
    public function __construct(
        private ResultRepository $results,
        private Vitals $vitals,
    ) {}

    /**
     * @param  Collection<int, TenantReference>  $tenants
     * @return Collection<int, ChangeEntry>
     */
    public function latest(Collection $tenants, int $limit = 12): Collection
    {
        $names = $tenants->mapWithKeys(fn (TenantReference $tenant): array => [$tenant->key => $tenant->name]);

        return $this->results->changes($limit)->map(function (CheckChange $change) use ($names): ChangeEntry {
            $scope = $change->tenant_key === null ? Scope::Central : Scope::Tenant;
            $check = $this->vitals->find($scope, $change->check_key);

            return new ChangeEntry(
                change: $change,
                checkLabel: $check?->label() ?? $change->check_key,
                tenantName: $change->tenant_key === null ? null : ($names->get($change->tenant_key) ?? $change->tenant_key),
                url: route('vitals.check', array_filter(['check' => $change->check_key, 'tenant' => $change->tenant_key])),
            );
        });
    }
}
