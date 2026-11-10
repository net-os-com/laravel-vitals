<?php

namespace NetOs\Vitals\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use NetOs\Vitals\Dashboard\Overview;
use NetOs\Vitals\Dashboard\RecentChanges;
use NetOs\Vitals\Dashboard\Snapshots;
use NetOs\Vitals\Dashboard\TenantSnapshot;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Scheduling\QueueBacklog;
use NetOs\Vitals\Vitals;

final readonly class OverviewController
{
    public function __construct(
        private Snapshots $snapshots,
        private Dispatcher $dispatcher,
        private Vitals $vitals,
        private Mutes $mutes,
        private RecentChanges $recentChanges,
        private QueueBacklog $queueBacklog,
        private Factory $views,
    ) {}

    public function __invoke(): View
    {
        $now = CarbonImmutable::now();
        $tenants = $this->snapshots->tenants($now);
        $label = config('vitals.dashboard.environment_label');
        $backlog = $this->queueBacklog->measure();

        return $this->views->make('vitals::overview', ['overview' => new Overview(
            now: $now,
            environment: is_string($label) && $label !== '' ? $label : Str::headline((string) app()->environment()),
            central: $this->snapshots->central($now),
            tenants: $tenants,
            usesTenancy: $tenants->isNotEmpty() || $this->vitals->checks(Scope::Tenant)->isNotEmpty(),
            heartbeat: $this->dispatcher->lastHeartbeat(),
            heartbeatStale: $this->dispatcher->isHeartbeatStale($now),
            mutes: $this->mutes->active($now),
            changes: $this->recentChanges->latest($tenants->map(fn (TenantSnapshot $tenant) => $tenant->tenant)),
            queueBacklog: $backlog,
            queueBehind: $this->queueBacklog->isBehind($backlog),
        )]);
    }
}
