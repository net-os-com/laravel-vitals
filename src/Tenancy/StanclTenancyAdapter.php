<?php

namespace NetOs\Vitals\Tenancy;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Exceptions\TenantNotInitialized;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Tenancy;
use Throwable;

final class StanclTenancyAdapter implements TenancyAdapter
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly string $nameAttribute = 'name',
        private readonly bool $verifyDatabaseConnection = true,
    ) {}

    public function tenants(): Collection
    {
        $query = $this->tenancy->query();

        if (method_exists($this->tenancy->model(), 'domains')) {
            $query->with('domains');
        }

        return $query->get()
            ->map(fn (Model $tenant): TenantReference => $this->reference($tenant))
            ->sortBy(fn (TenantReference $tenant): string => mb_strtolower($tenant->name))
            ->values();
    }

    public function find(string $key): ?TenantReference
    {
        $tenant = $this->tenancy->find($key);

        return $tenant instanceof Model ? $this->reference($tenant) : null;
    }

    public function run(TenantReference $tenant, Closure $callback): mixed
    {
        try {
            $model = $tenant->model instanceof Tenant ? $tenant->model : $this->tenancy->find($tenant->key);

            if ($model === null) {
                throw new \RuntimeException("Tenant [{$tenant->key}] does not exist");
            }

            $this->tenancy->initialize($model);

            if ($this->verifyDatabaseConnection && $model instanceof TenantWithDatabase) {
                DB::connection()->getPdo();
            }
        } catch (Throwable $exception) {
            $this->tenancy->end();

            throw TenantNotInitialized::because($exception);
        }

        try {
            return $callback();
        } finally {
            $this->tenancy->end();
        }
    }

    private function reference(Model $tenant): TenantReference
    {
        $key = (string) $tenant->getKey();
        $name = $tenant->getAttribute($this->nameAttribute);

        $domain = method_exists($tenant, 'domains')
            ? $tenant->getRelationValue('domains')?->first()?->getAttribute('domain')
            : null;

        return new TenantReference(
            key: $key,
            name: is_string($name) && $name !== '' ? $name : $key,
            domain: is_string($domain) ? $domain : null,
            model: $tenant,
        );
    }
}
