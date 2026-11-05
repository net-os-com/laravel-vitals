<?php

namespace NetOs\Vitals\Tests\Fixtures;

use Closure;
use Illuminate\Support\Collection;
use NetOs\Vitals\Exceptions\TenantNotInitialized;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tenancy\TenantReference;
use RuntimeException;

final class FakeTenancyAdapter implements TenancyAdapter
{
    public ?string $current = null;

    /** @var array<int, string> */
    public array $ended = [];

    /**
     * @param  array<int, TenantReference>  $tenants
     * @param  array<int, string>  $broken  keys of tenants that cannot be initialized
     */
    public function __construct(private array $tenants = [], private array $broken = []) {}

    public function tenants(): Collection
    {
        return new Collection($this->tenants);
    }

    public function find(string $key): ?TenantReference
    {
        return $this->tenants()->first(fn (TenantReference $tenant): bool => $tenant->key === $key);
    }

    public function run(TenantReference $tenant, Closure $callback): mixed
    {
        if (in_array($tenant->key, $this->broken, true)) {
            throw TenantNotInitialized::because(new RuntimeException('connection refused'));
        }

        $this->current = $tenant->key;

        try {
            return $callback();
        } finally {
            $this->current = null;
            $this->ended[] = $tenant->key;
        }
    }
}
