<?php

namespace NetOs\Vitals\Checks;

use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class DatabaseCheck extends Check
{
    protected ?string $connection = null;

    public function connection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function run(): Result
    {
        $start = hrtime(true);
        $connection = DB::connection($this->connection);
        $connection->select('select 1');
        $milliseconds = (int) round((hrtime(true) - $start) / 1_000_000);

        return Result::ok("Connected in {$milliseconds} ms")->meta([
            'connection' => $connection->getName(),
            'driver' => $connection->getDriverName(),
            'milliseconds' => $milliseconds,
        ]);
    }
}
