<?php

namespace NetOs\Vitals;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use NetOs\Vitals\Commands\VitalsCommand;

class VitalsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-vitals')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_vitals_table')
            ->hasCommand(VitalsCommand::class);
    }
}
