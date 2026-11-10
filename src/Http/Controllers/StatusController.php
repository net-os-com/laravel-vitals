<?php

namespace NetOs\Vitals\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use NetOs\Vitals\Dashboard\CheckSnapshot;
use NetOs\Vitals\Dashboard\Health;
use NetOs\Vitals\Dashboard\Snapshots;
use NetOs\Vitals\Dashboard\TenantSnapshot;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Scheduling\QueueBacklog;

/**
 * For an external monitor: 503 when a check fails, crashed or is stale, or when the dispatcher stopped.
 */
final readonly class StatusController
{
    public function __construct(
        private Snapshots $snapshots,
        private Dispatcher $dispatcher,
        private QueueBacklog $queueBacklog,
    ) {}

    public function __invoke(): JsonResponse
    {
        $now = CarbonImmutable::now();

        $checks = $this->snapshots->central($now)->concat(
            $this->snapshots->tenants($now)->flatMap(fn (TenantSnapshot $tenant) => $tenant->applicable()),
        );

        $problems = $checks->filter(fn (CheckSnapshot $check): bool => $check->needsAttention())->values();
        $muted = $checks->filter(fn (CheckSnapshot $check): bool => $check->isMutedProblem())->values();
        $heartbeatStale = $this->dispatcher->isHeartbeatStale($now);
        $backlog = $this->queueBacklog->measure();
        $queueBehind = $this->queueBacklog->isBehind($backlog);

        $unhealthy = $heartbeatStale || $queueBehind || $problems->contains(
            fn (CheckSnapshot $check): bool => $check->health->isFailing() || $check->health === Health::Stale,
        );

        $status = match (true) {
            $unhealthy => 'failing',
            $problems->isNotEmpty() => 'warning',
            default => 'ok',
        };

        return response()->json([
            'status' => $status,
            'checked_at' => $now->toIso8601String(),
            'dispatcher' => [
                'last_heartbeat_at' => $this->dispatcher->lastHeartbeat()?->toIso8601String(),
                'stale' => $heartbeatStale,
            ],
            'queue' => [...$backlog, 'behind' => $queueBehind],
            'checks' => $checks->count(),
            'problems' => $problems->map(fn (CheckSnapshot $check): array => [
                'check' => $check->check->key(),
                'tenant' => $check->tenant?->key,
                'status' => $check->health->value,
                'summary' => $check->summary(),
                'last_run_at' => $check->lastRunAt()?->toIso8601String(),
                'url' => $check->url(),
                'runbook' => $check->check->check->runbookUrl(),
            ])->all(),
            'muted' => $muted->map(fn (CheckSnapshot $check): array => [
                'check' => $check->check->key(),
                'tenant' => $check->tenant?->key,
                'status' => $check->health->value,
                'summary' => $check->summary(),
                'muted_until' => $check->mute?->until?->toIso8601String(),
                'until_recovered' => (bool) $check->mute?->until_recovered,
                'reason' => $check->mute?->reason,
                'url' => $check->url(),
            ])->all(),
        ], $unhealthy ? 503 : 200);
    }
}
