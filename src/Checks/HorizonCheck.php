<?php

namespace NetOs\Vitals\Checks;

use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

/**
 * Requires laravel/horizon. Skipped when Horizon is not installed.
 */
class HorizonCheck extends Check
{
    private const string REPOSITORY = 'Laravel\Horizon\Contracts\MasterSupervisorRepository';

    public function run(): Result
    {
        if (! interface_exists(self::REPOSITORY)) {
            return Result::skipped('Horizon is not installed');
        }

        /** @var array<int, object{status?: string}> $masters */
        $masters = app(self::REPOSITORY)->all();

        if ($masters === []) {
            return Result::failed('Horizon is not running');
        }

        $paused = collect($masters)->filter(fn (object $master): bool => ($master->status ?? null) === 'paused')->count();
        $count = count($masters);

        if ($paused > 0) {
            return Result::warning("{$paused} of {$count} Horizon ".str('supervisor')->plural($count).' paused')
                ->meta(['masters' => $count, 'paused' => $paused]);
        }

        return Result::ok("Running with {$count} ".str('supervisor')->plural($count))->meta(['masters' => $count]);
    }
}
