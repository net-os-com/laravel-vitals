<?php

namespace NetOs\Vitals\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use NetOs\Vitals\Dashboard\CheckDetail;
use NetOs\Vitals\Dashboard\CheckSnapshot;
use NetOs\Vitals\Dashboard\Flapping;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Storage\ResultRepository;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Vitals;

final readonly class CheckController
{
    public function __construct(
        private Vitals $vitals,
        private TenancyAdapter $tenancy,
        private ResultRepository $results,
        private Factory $views,
        private Mutes $mutes,
    ) {}

    public function __invoke(Request $request, string $check): View
    {
        $tenantKey = $request->query('tenant');
        $tenant = is_string($tenantKey) && $tenantKey !== '' ? $this->tenancy->find($tenantKey) : null;

        abort_if(is_string($tenantKey) && $tenantKey !== '' && $tenant === null, 404);

        $registered = $this->vitals->find($tenant === null ? Scope::Central : Scope::Tenant, $check);

        abort_if($registered === null, 404);

        $range = $request->query('range');
        $range = is_string($range) && array_key_exists($range, CheckDetail::RANGES) ? $range : '7d';
        $now = CarbonImmutable::now();
        $since = CheckDetail::since($range, $now);
        $state = $this->results->state($check, $tenant?->key);

        return $this->views->make('vitals::check', ['detail' => new CheckDetail(
            snapshot: CheckSnapshot::make(
                $registered,
                $tenant,
                $state,
                $now,
                $this->results->recentStatuses($tenant?->key, Flapping::WINDOW)->get($check.'|'.$tenant?->key),
                $this->mutes->covering($this->mutes->active($now), $registered, $tenant?->key),
            ),
            range: $range,
            since: $since,
            now: $now,
            history: $this->results->history($check, $tenant?->key, $since),
            runs: $this->results->paginate($check, $tenant?->key, $since, 24)->withQueryString(),
            latest: $this->results->latest($check, $tenant?->key),
        )]);
    }
}
