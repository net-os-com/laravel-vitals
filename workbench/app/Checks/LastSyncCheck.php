<?php

namespace Workbench\App\Checks;

use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

/**
 * Example of an app-specific tenant check: did the last sync with an external system succeed?
 */
class LastSyncCheck extends Check
{
    public function run(): Result
    {
        $sync = DB::table('syncs')->latest('synced_at')->first();

        if ($sync === null) {
            return Result::warning('No sync has run yet');
        }

        $meta = ['last_sync' => (string) $sync->synced_at, 'message' => (string) $sync->message];

        return $sync->succeeded
            ? Result::ok((string) $sync->message)->meta($meta)
            : Result::failed((string) $sync->message)->meta($meta);
    }
}
