<?php

use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Tests\Fixtures\FakeTenancyAdapter;
use NetOs\Vitals\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * @param  array<int, string>  $keys
 * @param  array<int, string>  $broken
 */
function fakeTenants(array $keys = ['acme', 'globex'], array $broken = []): FakeTenancyAdapter
{
    $adapter = new FakeTenancyAdapter(
        array_map(fn (string $key): TenantReference => new TenantReference($key, ucfirst($key), "{$key}.test"), $keys),
        $broken,
    );

    app()->instance(TenancyAdapter::class, $adapter);

    return $adapter;
}
