<?php

namespace NetOs\Vitals\Tenancy;

use Closure;
use Illuminate\Support\Collection;

interface TenancyAdapter
{
    /**
     * @return Collection<int, TenantReference>
     */
    public function tenants(): Collection;

    public function find(string $key): ?TenantReference;

    /**
     * Run the callback inside the tenant's context, and always leave that context afterwards.
     * Throws TenantNotInitialized when the context cannot be entered.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(TenantReference $tenant, Closure $callback): mixed;
}
