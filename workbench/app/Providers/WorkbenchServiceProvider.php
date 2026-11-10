<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use NetOs\Vitals\Checks\CacheCheck;
use NetOs\Vitals\Checks\DatabaseCheck;
use NetOs\Vitals\Checks\DebugModeCheck;
use NetOs\Vitals\Checks\DiskSpaceCheck;
use NetOs\Vitals\Checks\EnvironmentCheck;
use NetOs\Vitals\Checks\FailedJobsCheck;
use NetOs\Vitals\Checks\HorizonCheck;
use NetOs\Vitals\Checks\PendingMigrationsCheck;
use NetOs\Vitals\Checks\SchedulerHeartbeatCheck;
use NetOs\Vitals\Checks\TenantDatabaseCheck;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Tenancy\TenantReference;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;
use Workbench\App\Checks\DeskCountCheck;
use Workbench\App\Checks\KioskCheck;
use Workbench\App\Checks\LastSyncCheck;
use Workbench\App\Models\Tenant;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'tenancy.tenant_model' => Tenant::class,
            'tenancy.central_domains' => ['127.0.0.1', 'localhost'],
            'tenancy.bootstrappers' => [DatabaseTenancyBootstrapper::class],
            'tenancy.features' => [],
            'tenancy.database.central_connection' => 'sqlite',
            'tenancy.database.suffix' => '.sqlite',
            'tenancy.migration_parameters' => [
                '--force' => true,
                '--path' => [realpath(__DIR__.'/../../database/migrations/tenant')],
                '--realpath' => true,
            ],
        ]);
    }

    public function boot(): void
    {
        // What the TenancyServiceProvider stub of stancl/tenancy does in an app.
        Event::listen(TenancyInitialized::class, BootstrapTenancy::class);
        Event::listen(TenancyEnded::class, RevertToCentralContext::class);

        Vitals::central([
            DatabaseCheck::new(),
            CacheCheck::new(),
            DiskSpaceCheck::new(),
            FailedJobsCheck::new(),
            SchedulerHeartbeatCheck::new()->everyMinute(),
            HorizonCheck::new(),
            DebugModeCheck::new()->expectedToBe(true),
            EnvironmentCheck::new()->expect('local'),
        ]);

        Vitals::tenant([
            TenantDatabaseCheck::new(),
            PendingMigrationsCheck::new()->hourly(),
            LastSyncCheck::new()->withLabel('External sync')->runbook('https://example.com/runbooks/external-sync'),
            DeskCountCheck::new()->withLabel('Desks'),
            KioskCheck::new()->withLabel('Visitor kiosk')->onlyFor(
                fn (TenantReference $tenant): bool => $tenant->model instanceof Tenant && (bool) $tenant->model->kiosk,
            ),
        ]);
    }
}
