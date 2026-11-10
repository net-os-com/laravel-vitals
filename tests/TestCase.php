<?php

namespace NetOs\Vitals\Tests;

use NetOs\Vitals\VitalsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            VitalsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('vitals.schedule', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach (['create_vitals_tables', 'create_vitals_mutes_table'] as $stub) {
            $migration = include __DIR__."/../database/migrations/{$stub}.php.stub";
            $migration->up();
        }
    }
}
