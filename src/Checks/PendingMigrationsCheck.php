<?php

namespace NetOs\Vitals\Checks;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

/**
 * Compares the migration files with the migrations table of the default connection. As a tenant check
 * it uses stancl's tenant migration paths unless you pass your own.
 */
class PendingMigrationsCheck extends Check
{
    /** @var array<int, string>|null */
    protected ?array $paths = null;

    protected bool $failWhenPending = false;

    /**
     * @param  array<int, string>  $paths
     */
    public function paths(array $paths): static
    {
        $this->paths = $paths;

        return $this;
    }

    public function failWhenPending(bool $fail = true): static
    {
        $this->failWhenPending = $fail;

        return $this;
    }

    public function run(): Result
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        return $migrator->usingConnection(DB::getDefaultConnection(), function () use ($migrator): Result {
            if (! $migrator->repositoryExists()) {
                return Result::failed('The migrations table does not exist');
            }

            $files = array_keys($migrator->getMigrationFiles($this->resolvePaths()));
            $pending = array_values(array_diff($files, $migrator->getRepository()->getRan()));
            $count = count($pending);

            if ($count === 0) {
                return Result::ok('No pending migrations')->meta(['migrations' => count($files)]);
            }

            $summary = "{$count} pending ".str('migration')->plural($count);
            $meta = ['pending' => $count, 'first_pending' => $pending[0]];

            return $this->failWhenPending ? Result::failed($summary)->meta($meta) : Result::warning($summary)->meta($meta);
        });
    }

    /**
     * @return array<int, string>
     */
    private function resolvePaths(): array
    {
        if ($this->paths !== null) {
            return $this->paths;
        }

        $tenantPaths = config('tenancy.migration_parameters.--path');

        if (function_exists('tenancy') && tenancy()->initialized && is_array($tenantPaths)) {
            return array_values(array_filter($tenantPaths, 'is_string'));
        }

        return [database_path('migrations')];
    }
}
