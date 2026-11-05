<?php

namespace NetOs\Vitals\Tenancy;

use Closure;
use Illuminate\Support\Collection;

final class NullTenancyAdapter implements TenancyAdapter
{
    public function tenants(): Collection
    {
        return new Collection;
    }

    public function find(string $key): ?TenantReference
    {
        return null;
    }

    public function run(TenantReference $tenant, Closure $callback): mixed
    {
        return $callback();
    }
}
