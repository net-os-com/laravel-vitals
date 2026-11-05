<?php

namespace NetOs\Vitals\Checks;

use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

/**
 * Runs inside the tenant context, so the default connection is the tenant database.
 */
class TenantDatabaseCheck extends Check
{
    public function key(): string
    {
        return $this->key ?? 'tenant-database';
    }

    public function run(): Result
    {
        $start = hrtime(true);
        $connection = DB::connection();
        $connection->select('select 1');
        $milliseconds = (int) round((hrtime(true) - $start) / 1_000_000);

        return Result::ok("Connected in {$milliseconds} ms")->meta([
            'database' => $connection->getDatabaseName(),
            'milliseconds' => $milliseconds,
        ]);
    }
}
