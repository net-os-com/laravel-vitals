<?php

namespace NetOs\Vitals\Checks;

use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

/**
 * Whether config, routes and events are cached. Skipped in the local environment.
 */
class OptimizedAppCheck extends Check
{
    public function run(): Result
    {
        if (app()->environment('local')) {
            return Result::skipped('Not checked in the local environment');
        }

        $app = app();
        $missing = array_keys(array_filter([
            'config' => ! $app->configurationIsCached(),
            'routes' => ! $app->routesAreCached(),
            'events' => ! $app->eventsAreCached(),
        ]));

        if ($missing !== []) {
            return Result::warning('Not cached: '.implode(', ', $missing))->meta(['missing' => implode(',', $missing)]);
        }

        return Result::ok('Config, routes and events are cached');
    }
}
