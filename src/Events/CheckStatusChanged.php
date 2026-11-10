<?php

namespace NetOs\Vitals\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Running\CheckRun;
use NetOs\Vitals\Tenancy\TenantReference;

final class CheckStatusChanged
{
    use Dispatchable;

    /**
     * @param  Status|null  $from  null on the first run of a check
     * @param  bool  $muted  whether someone muted this check in the dashboard, so listeners can skip notifying
     */
    public function __construct(
        public readonly RegisteredCheck $check,
        public readonly ?TenantReference $tenant,
        public readonly ?Status $from,
        public readonly Status $to,
        public readonly CheckRun $run,
        public readonly bool $muted = false,
    ) {}
}
