<?php

namespace NetOs\Vitals\Storage;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Models\CheckChange;
use NetOs\Vitals\Models\CheckResult;
use NetOs\Vitals\Models\CheckState;
use NetOs\Vitals\Running\CheckRun;

final class EloquentResultRepository implements ResultRepository
{
    public function record(string $checkKey, ?string $tenantKey, CheckRun $run): ?Status
    {
        $result = $run->result;

        CheckResult::query()->create([
            'check_key' => $checkKey,
            'tenant_key' => $tenantKey,
            'status' => $result->status,
            'summary' => $result->summary,
            'meta' => $result->meta === [] ? null : $result->meta,
            'duration_ms' => $run->durationMs,
            'started_at' => $run->startedAt,
            'finished_at' => $run->finishedAt,
        ]);

        $state = $this->state($checkKey, $tenantKey) ?? new CheckState([
            'check_key' => $checkKey,
            'tenant_key' => $tenantKey,
            'consecutive_failures' => 0,
        ]);

        $previous = $state->exists ? $state->status : null;

        if ($previous !== null && $previous !== $result->status) {
            CheckChange::query()->create([
                'check_key' => $checkKey,
                'tenant_key' => $tenantKey,
                'from_status' => $previous,
                'to_status' => $result->status,
                'summary' => $result->summary,
                'changed_at' => $run->finishedAt,
            ]);
        }

        $state->fill([
            'status' => $result->status,
            'summary' => $result->summary,
            'last_run_at' => $run->finishedAt,
            'status_since' => $previous === $result->status ? $state->status_since : $run->finishedAt,
            'consecutive_failures' => $result->status->isFailing() ? $state->consecutive_failures + 1 : 0,
            'last_success_at' => $result->status === Status::Ok ? $run->finishedAt : $state->last_success_at,
        ])->save();

        return $previous;
    }

    public function states(?string $tenantKey = null, bool $allTenants = false): Collection
    {
        return $this->scoped(CheckState::query(), $tenantKey, $allTenants)->get();
    }

    public function state(string $checkKey, ?string $tenantKey): ?CheckState
    {
        return CheckState::query()
            ->where('check_key', $checkKey)
            ->where('tenant_key', $tenantKey)
            ->first();
    }

    public function recentStatuses(?string $tenantKey, int $limit, bool $allTenants = false): Collection
    {
        $ranked = $this->scoped(CheckResult::query(), $tenantKey, $allTenants)
            ->select(['check_key', 'tenant_key', 'status', 'finished_at'])
            ->selectRaw('row_number() over (partition by check_key, tenant_key order by finished_at desc, id desc) as position');

        return CheckResult::query()
            ->fromSub($ranked, 'ranked')
            ->where('position', '<=', $limit)
            ->orderBy('position')
            ->get()
            ->groupBy(fn (CheckResult $result): string => $result->check_key.'|'.$result->tenant_key)
            ->map(fn (Collection $results): Collection => $results->map(fn (CheckResult $result): Status => $result->status)->values());
    }

    public function history(string $checkKey, ?string $tenantKey, CarbonInterface $since): Collection
    {
        return $this->forCheck($checkKey, $tenantKey, $since)
            ->orderBy('finished_at')
            ->orderBy('id')
            ->get(['id', 'status', 'duration_ms', 'finished_at']);
    }

    public function paginate(string $checkKey, ?string $tenantKey, CarbonInterface $since, int $perPage): LengthAwarePaginator
    {
        return $this->forCheck($checkKey, $tenantKey, $since)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function latest(string $checkKey, ?string $tenantKey): ?CheckResult
    {
        return CheckResult::query()
            ->where('check_key', $checkKey)
            ->where('tenant_key', $tenantKey)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();
    }

    public function changes(int $limit): Collection
    {
        return CheckChange::query()->orderByDesc('changed_at')->orderByDesc('id')->limit($limit)->get();
    }

    public function prune(CarbonInterface $before): int
    {
        CheckChange::query()->where('changed_at', '<', $before)->delete();

        return CheckResult::query()->where('finished_at', '<', $before)->delete();
    }

    /**
     * @return Builder<CheckResult>
     */
    private function forCheck(string $checkKey, ?string $tenantKey, CarbonInterface $since): Builder
    {
        return CheckResult::query()
            ->where('check_key', $checkKey)
            ->where('tenant_key', $tenantKey)
            ->where('finished_at', '>=', $since);
    }

    /**
     * @template TModel of CheckResult|CheckState
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scoped(Builder $query, ?string $tenantKey, bool $allTenants): Builder
    {
        if ($allTenants) {
            return $query->whereNotNull('tenant_key');
        }

        return $query->where('tenant_key', $tenantKey);
    }
}
