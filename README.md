# Laravel Vitals

[![Latest Version on Packagist](https://img.shields.io/packagist/v/net-os/laravel-vitals.svg?style=flat-square)](https://packagist.org/packages/net-os/laravel-vitals)
[![Tests](https://github.com/net-os/laravel-vitals/actions/workflows/run-tests.yml/badge.svg)](https://github.com/net-os/laravel-vitals/actions/workflows/run-tests.yml)

Health checks for Laravel, both for the whole application and per tenant, with a dashboard in the style of Horizon.

- You write checks as small PHP classes and register them in code, either centrally or per tenant.
- A dispatcher runs every minute and puts the checks that are due on the queue. A tenant's checks run together in one job, inside a single tenancy initialization.
- Results go to the central database: `vitals_results` holds the history (14 days by default) and `vitals_states` holds the latest state.
- A server-rendered Blade dashboard lives at `/vitals`, behind a configurable gate.
- `/vitals/status.json` returns 200 or 503 for an external monitor.
- There are no notification channels. Listen to the `CheckStatusChanged` and `CheckFinished` events to add your own.

Tenancy is optional. Vitals detects [stancl/tenancy](https://tenancyforlaravel.com) on its own, and you can plug in any other tenancy package with a `TenancyAdapter`.

## Installation

```bash
composer require net-os/laravel-vitals
php artisan vitals:install
```

The install command publishes `config/vitals.php` and the migration, and offers to run the migration.

## Registering checks

Register checks in a service provider:

```php
use NetOs\Vitals\Checks;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Tenancy\TenantReference;

Vitals::central([
    Checks\DatabaseCheck::new(),
    Checks\CacheCheck::new(),
    Checks\RedisCheck::new()->failWhenMemoryAboveMb(500),
    Checks\DiskSpaceCheck::new()->warnWhenUsedAbove(80)->failWhenUsedAbove(90),
    Checks\FailedJobsCheck::new(),
    Checks\HorizonCheck::new(),
    Checks\SchedulerHeartbeatCheck::new()->everyMinute(),
    Checks\DebugModeCheck::new(),
    Checks\EnvironmentCheck::new(),
    Checks\OptimizedAppCheck::new(),
]);

Vitals::tenant([
    Checks\TenantDatabaseCheck::new(),
    Checks\PendingMigrationsCheck::new()->hourly(),
    DeviceSyncCheck::new()->onlyFor(fn (TenantReference $tenant) => $tenant->model->hasDevices()),
]);
```

Central checks run every 5 minutes and tenant checks every 15 minutes, unless a check sets its own frequency. The available frequencies are `everyMinute()`, `everyFiveMinutes()`, `everyTenMinutes()`, `everyFifteenMinutes()`, `everyThirtyMinutes()`, `hourly()`, `hourlyAt(15)`, `daily()`, `dailyAt('06:00')` and `cron('…')`. Vitals spreads tenants over the interval using a stable offset based on the tenant key, so they do not all run in the same minute.

A check registered with `onlyFor()` runs only for the tenants that pass the callback. The dashboard shows it as "Not applicable" for every other tenant.

If two checks in the same scope have the same key, Vitals throws an exception. Use `->withKey('…')` to tell them apart, and `->withLabel('…')` to change the name the dashboard shows.

## Writing a check

```php
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class Auth0SyncCheck extends Check
{
    public function run(): Result
    {
        $lastSync = SyncLog::latest()->first();

        if ($lastSync === null) {
            return Result::warning('No sync has run yet');
        }

        return $lastSync->succeeded
            ? Result::ok("Synced {$lastSync->users} users")
            : Result::failed($lastSync->error)->meta(['last_sync' => $lastSync->created_at->toDateTimeString()]);
    }
}
```

A check returns `ok`, `warning`, `failed` or `skipped`. Vitals marks a check as `crashed` when it throws an exception or runs past its timeout (30 seconds by default, change it with `->timeout(10)`). The exception is passed to `report()`. A tenant check runs inside the tenant context, so the default database connection is the tenant's database.

## Running

Vitals adds `vitals:dispatch` (every minute, on one server) and `vitals:prune` (daily) to the scheduler. You only need `schedule:run` and a queue worker. To schedule them yourself, set `vitals.schedule` to `false`.

| Command | Purpose |
| --- | --- |
| `vitals:dispatch {--all}` | Put the checks that are due on the queue |
| `vitals:run {check?} {--tenant=}` | Run checks synchronously and print a table |
| `vitals:list` | List the registered checks with their schedule |
| `vitals:prune {--days=}` | Delete results older than `vitals.retention_days` |

Give Vitals its own queue (`VITALS_QUEUE=vitals`), so a busy default queue does not delay the checks.

## Dashboard access

Access is decided in this order:

1. A callback, if you set one: `Vitals::auth(fn (Request $request) => …)`.
2. Otherwise the `viewVitals` gate, if it is defined.
3. Otherwise only the `local` environment.

`status.json` also accepts the `X-Vitals-Token` header when `VITALS_STATUS_TOKEN` is set. It returns 503 when a check has failed, crashed or gone stale, or when the dispatcher has not run for 3 minutes. A check counts as stale when it has not reported for twice its interval plus 5 minutes.

## Tenancy

With stancl/tenancy installed, Vitals uses `StanclTenancyAdapter`. For each tenant it initializes tenancy once, runs all due checks, and ends tenancy in a `finally` block. If the tenant cannot be initialized, or its database cannot be reached, every check of that tenant is recorded as crashed with "Tenant could not be initialized". Results are always written to the central connection.

For any other tenancy package, implement `NetOs\Vitals\Tenancy\TenancyAdapter` (`tenants()`, `find()` and `run()`) and set `vitals.tenancy.adapter` to your class.

## Events

- `CheckFinished($check, $tenant, $run)` is dispatched after every run.
- `CheckStatusChanged($check, $tenant, $from, $to, $run)` is dispatched when the status changes, including on the first run, where `$from` is `null`.

## Development

```bash
composer test      # Pest
composer analyse   # Larastan (level 8)
composer format    # Pint
composer serve     # build the workbench and open http://127.0.0.1:8000/vitals
```

The workbench has three stancl tenants. Acme has a failing sync, Globex has a pending migration and Northwind has no database. It also contains seven days of history.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
