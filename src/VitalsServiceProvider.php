<?php

namespace NetOs\Vitals;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use NetOs\Vitals\Commands\DispatchCommand;
use NetOs\Vitals\Commands\ListCommand;
use NetOs\Vitals\Commands\MuteCommand;
use NetOs\Vitals\Commands\PruneCommand;
use NetOs\Vitals\Commands\RunCommand;
use NetOs\Vitals\Commands\UnmuteCommand;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Storage\EloquentResultRepository;
use NetOs\Vitals\Storage\ResultRepository;
use NetOs\Vitals\Tenancy\NullTenancyAdapter;
use NetOs\Vitals\Tenancy\StanclTenancyAdapter;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Stancl\Tenancy\Tenancy;

class VitalsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-vitals')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web')
            ->hasMigrations(['create_vitals_tables', 'create_vitals_mutes_table', 'create_vitals_changes_table'])
            ->hasCommands([
                DispatchCommand::class,
                RunCommand::class,
                ListCommand::class,
                PruneCommand::class,
                MuteCommand::class,
                UnmuteCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Vitals::class, function (): Vitals {
            $frequencies = config('vitals.frequencies', []);

            return new Vitals(is_array($frequencies) ? array_filter($frequencies, 'is_string') : []);
        });

        $this->app->singleton(ResultRepository::class, EloquentResultRepository::class);

        $this->app->singleton(TenancyAdapter::class, function (Application $app): TenancyAdapter {
            $adapter = config('vitals.tenancy.adapter');

            if (is_string($adapter) && $adapter !== '') {
                /** @var TenancyAdapter */
                return $app->make($adapter);
            }

            if (class_exists(Tenancy::class) && $app->bound(Tenancy::class)) {
                $nameAttribute = config('vitals.tenancy.name_attribute', 'name');

                return new StanclTenancyAdapter(
                    $app->make(Tenancy::class),
                    is_string($nameAttribute) ? $nameAttribute : 'name',
                    (bool) config('vitals.tenancy.verify_database_connection', true),
                );
            }

            return new NullTenancyAdapter;
        });

        $this->app->when(Dispatcher::class)
            ->needs(Repository::class)
            ->give(function (Application $app) {
                $store = config('vitals.cache_store');

                return $app->make('cache')->store(is_string($store) && $store !== '' ? $store : null);
            });
    }

    public function packageBooted(): void
    {
        $this->resolveConnection();

        if (config('vitals.schedule', true)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('vitals:dispatch')->everyMinute()->onOneServer()->withoutOverlapping()->runInBackground();
                $schedule->command('vitals:prune')->daily()->onOneServer();
            });
        }
    }

    /**
     * Pin the connection at boot, before a tenant can switch the default connection.
     */
    private function resolveConnection(): void
    {
        if (is_string(config('vitals.connection')) && config('vitals.connection') !== '') {
            return;
        }

        $central = config('tenancy.database.central_connection');

        config(['vitals.connection' => is_string($central) && config("database.connections.{$central}") !== null
            ? $central
            : config('database.default')]);
    }
}
