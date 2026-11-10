<?php

namespace NetOs\Vitals\Dashboard;

use Illuminate\Support\Collection;
use NetOs\Vitals\Models\Mute;
use NetOs\Vitals\Registry\RegisteredCheck;

/**
 * One tenant check over every tenant.
 */
final readonly class CheckAcrossTenants
{
    /**
     * @param  Collection<int, CheckSnapshot>  $snapshots  one per tenant
     */
    public function __construct(
        public RegisteredCheck $check,
        public Collection $snapshots,
        public ?Mute $allTenantsMute = null,
    ) {}

    /**
     * @param  Collection<int, TenantSnapshot>  $tenants
     */
    public static function from(RegisteredCheck $check, Collection $tenants, ?Mute $allTenantsMute = null): self
    {
        return new self($check, $tenants
            ->map(fn (TenantSnapshot $tenant): ?CheckSnapshot => $tenant->checks->first(
                fn (CheckSnapshot $snapshot): bool => $snapshot->check->key() === $check->key(),
            ))
            ->filter()
            ->values(), $allTenantsMute);
    }

    /**
     * Problems first, not applicable last.
     *
     * @return Collection<int, CheckSnapshot>
     */
    public function sorted(): Collection
    {
        return $this->snapshots
            ->sortBy(fn (CheckSnapshot $snapshot): array => [
                -$snapshot->attentionHealth()->severity(),
                -$snapshot->health->severity(),
                mb_strtolower($snapshot->tenant->name ?? ''),
            ])
            ->values();
    }

    public function count(Health ...$health): int
    {
        return $this->snapshots->filter(fn (CheckSnapshot $snapshot): bool => in_array($snapshot->attentionHealth(), $health, true))->count();
    }

    public function failing(): int
    {
        return $this->count(Health::Failed, Health::Crashed);
    }

    public function muted(): int
    {
        return $this->snapshots->filter(fn (CheckSnapshot $snapshot): bool => $snapshot->isMutedProblem())->count();
    }

    public function applicable(): int
    {
        return $this->snapshots->reject(fn (CheckSnapshot $snapshot): bool => $snapshot->health === Health::NotApplicable)->count();
    }

    public function worst(): Health
    {
        return $this->snapshots
            ->map(fn (CheckSnapshot $snapshot): Health => $snapshot->attentionHealth())
            ->sortByDesc(fn (Health $health): int => $health->severity())
            ->first() ?? Health::Pending;
    }
}
