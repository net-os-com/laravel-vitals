<?php

namespace NetOs\Vitals\Muting;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Models\CheckState;
use NetOs\Vitals\Models\Mute;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Tenancy\TenantReference;

final class Mutes
{
    /**
     * @return Collection<int, Mute>
     */
    public function active(CarbonInterface $now): Collection
    {
        return $this->activeQuery($now)->orderByDesc('created_at')->get();
    }

    /**
     * @param  Collection<int, Mute>  $active
     */
    public function covering(Collection $active, RegisteredCheck $check, ?string $tenantKey): ?Mute
    {
        return $active->first(fn (Mute $mute): bool => $mute->covers($check, $tenantKey));
    }

    public function isMuted(RegisteredCheck $check, ?TenantReference $tenant, CarbonInterface $now): bool
    {
        return $this->covering($this->active($now), $check, $tenant?->key) !== null;
    }

    public function mute(Scope $scope, ?string $checkKey, ?string $tenantKey, MuteDuration $duration, ?string $reason): Mute
    {
        $now = CarbonImmutable::now();

        return Mute::query()->create([
            'scope' => $scope,
            'check_key' => $checkKey,
            'tenant_key' => $tenantKey,
            'reason' => $reason === '' ? null : $reason,
            'until' => $duration->until($now),
            'until_recovered' => $duration === MuteDuration::UntilRecovered,
            'created_at' => $now,
        ]);
    }

    public function unmute(int $id): bool
    {
        return Mute::query()->whereKey($id)->delete() > 0;
    }

    /**
     * Lift the "until it recovers" mutes for this check once none of the checks they cover has a problem.
     */
    public function releaseRecovered(RegisteredCheck $check, ?TenantReference $tenant): void
    {
        $mutes = $this->activeQuery(CarbonImmutable::now())->where('until_recovered', true)->get()
            ->filter(fn (Mute $mute): bool => $mute->covers($check, $tenant?->key));

        foreach ($mutes as $mute) {
            if (! $this->hasProblems($mute)) {
                $mute->delete();
            }
        }
    }

    public function pruneExpired(CarbonInterface $now): int
    {
        return Mute::query()->whereNotNull('until')->where('until', '<=', $now)->delete();
    }

    private function hasProblems(Mute $mute): bool
    {
        return CheckState::query()
            ->when($mute->scope === Scope::Central, fn (Builder $query) => $query->whereNull('tenant_key'))
            ->when($mute->scope === Scope::Tenant, fn (Builder $query) => $query->whereNotNull('tenant_key'))
            ->when($mute->check_key !== null, fn (Builder $query) => $query->where('check_key', $mute->check_key))
            ->when($mute->tenant_key !== null, fn (Builder $query) => $query->where('tenant_key', $mute->tenant_key))
            ->whereNotIn('status', ['ok', 'skipped'])
            ->exists();
    }

    /**
     * @return Builder<Mute>
     */
    private function activeQuery(CarbonInterface $now): Builder
    {
        return Mute::query()->where(fn (Builder $query) => $query->whereNull('until')->orWhere('until', '>', $now));
    }
}
