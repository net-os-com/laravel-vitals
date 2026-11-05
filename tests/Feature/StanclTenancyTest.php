<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\ProcessTenantChecks;
use NetOs\Vitals\Tenancy\StanclTenancyAdapter;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;
use Stancl\Tenancy\TenancyServiceProvider;
use Workbench\App\Models\Tenant;

beforeEach(function () {
    $this->app->register(TenancyServiceProvider::class);

    config([
        'tenancy.tenant_model' => Tenant::class,
        'tenancy.bootstrappers' => [DatabaseTenancyBootstrapper::class],
        'tenancy.features' => [],
        'tenancy.database.central_connection' => 'testing',
        'tenancy.database.prefix' => 'vitals_test_',
        'tenancy.database.suffix' => '.sqlite',
    ]);

    Event::listen(TenancyInitialized::class, BootstrapTenancy::class);
    Event::listen(TenancyEnded::class, RevertToCentralContext::class);

    foreach (glob(__DIR__.'/../../vendor/stancl/tenancy/assets/migrations/*.php') as $migration) {
        require_once $migration;
    }

    (new CreateTenantsTable)->up();
    (new CreateDomainsTable)->up();

    foreach (['acme' => 'Acme Offices', 'globex' => 'Globex Campus'] as $key => $name) {
        $tenant = Tenant::query()->create(['id' => $key, 'name' => $name]);
        $tenant->domains()->create(['domain' => "{$key}.test"]);
        $tenant->database()->manager()->createDatabase($tenant);
    }

    Tenant::query()->create(['id' => 'northwind', 'name' => 'Northwind HQ']);

    Vitals::tenant([
        (new FixedCheck(fn () => Result::ok(basename((string) DB::connection()->getDatabaseName()))))->withKey('which-database'),
    ]);
});

afterEach(function () {
    tenancy()->end();

    foreach (glob(database_path('vitals_test_*.sqlite')) as $file) {
        unlink($file);
    }
});

it('resolves the stancl adapter and maps tenants', function () {
    $adapter = app(TenancyAdapter::class);

    expect($adapter)->toBeInstanceOf(StanclTenancyAdapter::class)
        ->and($adapter->tenants()->pluck('name')->all())->toBe(['Acme Offices', 'Globex Campus', 'Northwind HQ'])
        ->and($adapter->find('acme')->domain)->toBe('acme.test')
        ->and($adapter->find('nope'))->toBeNull();
});

it('runs tenants one after another without leaking the tenant context', function () {
    $adapter = app(TenancyAdapter::class);
    $process = app(ProcessTenantChecks::class);

    foreach ($adapter->tenants() as $tenant) {
        $process->handle($tenant, Vitals::checks(Scope::Tenant));
    }

    expect(tenancy()->initialized)->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('testing');

    $this->assertDatabaseHas('vitals_states', ['tenant_key' => 'acme', 'status' => 'ok', 'summary' => 'vitals_test_acme.sqlite']);
    $this->assertDatabaseHas('vitals_states', ['tenant_key' => 'globex', 'status' => 'ok', 'summary' => 'vitals_test_globex.sqlite']);
    $this->assertDatabaseHas('vitals_states', ['tenant_key' => 'northwind', 'status' => 'crashed']);

    expect(DB::table('vitals_states')->where('tenant_key', 'northwind')->value('summary'))
        ->toStartWith('Tenant could not be initialized: ');
});
