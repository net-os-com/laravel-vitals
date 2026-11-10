<?php

namespace NetOs\Vitals\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use NetOs\Vitals\Dashboard\Overview;
use NetOs\Vitals\Dashboard\Snapshots;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Vitals;

final readonly class OverviewController
{
    public function __construct(
        private Snapshots $snapshots,
        private Dispatcher $dispatcher,
        private Vitals $vitals,
        private Mutes $mutes,
        private Factory $views,
    ) {}

    public function __invoke(): View
    {
        $now = CarbonImmutable::now();
        $tenants = $this->snapshots->tenants($now);
        $label = config('vitals.dashboard.environment_label');

        return $this->views->make('vitals::overview', ['overview' => new Overview(
            now: $now,
            environment: is_string($label) && $label !== '' ? $label : Str::headline((string) app()->environment()),
            central: $this->snapshots->central($now),
            tenants: $tenants,
            usesTenancy: $tenants->isNotEmpty() || $this->vitals->checks(Scope::Tenant)->isNotEmpty(),
            heartbeat: $this->dispatcher->lastHeartbeat(),
            heartbeatStale: $this->dispatcher->isHeartbeatStale($now),
            mutes: $this->mutes->active($now),
        )]);
    }
}
