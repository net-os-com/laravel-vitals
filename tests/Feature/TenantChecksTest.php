<?php

use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Jobs\RunTenantChecksJob;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\ProcessTenantChecks;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

it('runs all checks of a tenant inside one initialization and always ends it', function () {
    $tenancy = fakeTenants();
    $seen = [];

    Vitals::tenant([
        (new FixedCheck(function () use ($tenancy, &$seen): Result {
            $seen[] = $tenancy->current;

            return Result::ok();
        }))->withKey('first'),
        (new FixedCheck(fn () => throw new RuntimeException('Boom')))->withKey('second'),
    ]);

    $runs = app(ProcessTenantChecks::class)->handle($tenancy->find('acme'), Vitals::checks(Scope::Tenant));

    expect($seen)->toBe(['acme'])
        ->and($runs['first']->result->status)->toBe(Status::Ok)
        ->and($runs['second']->result->status)->toBe(Status::Crashed)
        ->and($tenancy->ended)->toBe(['acme'])
        ->and($tenancy->current)->toBeNull();

    $this->assertDatabaseHas('vitals_states', ['check_key' => 'first', 'tenant_key' => 'acme', 'status' => 'ok']);
    $this->assertDatabaseHas('vitals_states', ['check_key' => 'second', 'tenant_key' => 'acme', 'status' => 'crashed']);
});

it('marks every check as crashed when the tenant cannot be initialized', function () {
    $tenancy = fakeTenants(['acme', 'northwind'], broken: ['northwind']);

    Vitals::tenant([
        (new FixedCheck(Result::ok()))->withKey('first'),
        (new FixedCheck(Result::ok()))->withKey('second'),
    ]);

    app(ProcessTenantChecks::class)->handle($tenancy->find('northwind'), Vitals::checks(Scope::Tenant));

    $this->assertDatabaseCount('vitals_results', 2);
    $this->assertDatabaseHas('vitals_states', [
        'check_key' => 'first',
        'tenant_key' => 'northwind',
        'status' => 'crashed',
        'summary' => 'Tenant could not be initialized: connection refused',
    ]);
});

it('only runs onlyFor checks for the tenants they apply to', function () {
    fakeTenants();

    Vitals::tenant([
        (new FixedCheck(Result::ok()))->withKey('everyone'),
        (new FixedCheck(Result::ok()))->withKey('acme-only')->onlyFor(fn (TenantReference $tenant) => $tenant->key === 'acme'),
    ]);

    (new RunTenantChecksJob('globex', ['everyone', 'acme-only']))->handle(
        app(NetOs\Vitals\Vitals::class),
        app(TenancyAdapter::class),
        app(ProcessTenantChecks::class),
    );

    $this->assertDatabaseHas('vitals_states', ['check_key' => 'everyone', 'tenant_key' => 'globex']);
    $this->assertDatabaseMissing('vitals_states', ['check_key' => 'acme-only', 'tenant_key' => 'globex']);
});
