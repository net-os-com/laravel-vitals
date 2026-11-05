<?php

namespace NetOs\Vitals\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Running\CheckRun;
use NetOs\Vitals\Tenancy\TenantReference;

final class CheckFinished
{
    use Dispatchable;

    public function __construct(
        public readonly RegisteredCheck $check,
        public readonly ?TenantReference $tenant,
        public readonly CheckRun $run,
    ) {}
}
