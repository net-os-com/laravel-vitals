<?php

namespace NetOs\Vitals\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use NetOs\Vitals\Models\Mute;

final readonly class Overview
{
    /**
     * @param  Collection<int, CheckSnapshot>  $central
     * @param  Collection<int, TenantSnapshot>  $tenants
     * @param  Collection<int, Mute>  $mutes
     * @param  Collection<int, ChangeEntry>  $changes
     * @param  array{queue: string, size: int|null, oldest_seconds: int|null}  $queueBacklog
     */
    public function __construct(
        public CarbonImmutable $now,
        public string $environment,
        public Collection $central,
        public Collection $tenants,
        public bool $usesTenancy,
        public ?CarbonImmutable $heartbeat,
        public bool $heartbeatStale,
        public Collection $mutes = new Collection,
        public Collection $changes = new Collection,
        public array $queueBacklog = ['queue' => 'default', 'size' => null, 'oldest_seconds' => null],
        public bool $queueBehind = false,
    ) {}

    /**
     * @return Collection<int, TenantSnapshot>
     */
    public function attention(): Collection
    {
        return $this->tenants
            ->filter(fn (TenantSnapshot $tenant): bool => $tenant->needsAttention())
            ->sortByDesc(fn (TenantSnapshot $tenant): array => [$tenant->isUnreachable(), $tenant->health()->severity(), $tenant->failing()])
            ->values();
    }

    /**
     * @return Collection<int, CheckSnapshot>
     */
    public function centralProblems(): Collection
    {
        return $this->central->filter(fn (CheckSnapshot $check): bool => $check->needsAttention())->values();
    }

    public function centralHealthy(): int
    {
        return $this->central->filter(fn (CheckSnapshot $check): bool => $check->health->severity() === 0)->count();
    }

    /**
     * Every tenant check over all tenants, for the "Tenant checks" section.
     *
     * @return Collection<int, CheckAcrossTenants>
     */
    public function tenantChecks(): Collection
    {
        $first = $this->tenants->first();

        if ($first === null) {
            return new Collection;
        }

        return $first->checks->map(fn (CheckSnapshot $snapshot): CheckAcrossTenants => CheckAcrossTenants::from($snapshot->check, $this->tenants));
    }

    public function headline(): string
    {
        $tenants = $this->attention()->count();
        $central = $this->centralProblems()->count();

        return match (true) {
            $tenants > 0 => $tenants.' '.str('tenant')->plural($tenants).' '.($tenants === 1 ? 'needs' : 'need').' attention',
            $central > 0 => $central.' central '.str('check')->plural($central).' '.($central === 1 ? 'needs' : 'need').' attention',
            $this->central->isEmpty() && $this->tenants->isEmpty() => 'No checks registered yet',
            default => 'Everything is healthy',
        };
    }

    /**
     * The four counters at the top: per tenant with tenancy, per central check without.
     *
     * @return array<int, array{value: int, label: string, tone: string}>
     */
    public function stats(): array
    {
        if ($this->usesTenancy) {
            $healths = $this->tenants->map(fn (TenantSnapshot $tenant): Health => $tenant->health());

            return $this->counters($healths, 'Failing tenants', 'Healthy tenants');
        }

        return $this->counters($this->central->map(fn (CheckSnapshot $check): Health => $check->attentionHealth()), 'Failing checks', 'Healthy checks');
    }

    /**
     * @param  Collection<int, Health>  $healths
     * @return array<int, array{value: int, label: string, tone: string}>
     */
    private function counters(Collection $healths, string $failing, string $healthy): array
    {
        return [
            ['value' => $healths->filter(fn (Health $health): bool => $health->isFailing())->count(), 'label' => $failing, 'tone' => 'fail'],
            ['value' => $healths->filter(fn (Health $health): bool => $health === Health::Warning)->count(), 'label' => 'Warnings', 'tone' => 'warn'],
            ['value' => $healths->filter(fn (Health $health): bool => $health === Health::Stale)->count(), 'label' => 'Stale', 'tone' => 'idle'],
            ['value' => $healths->filter(fn (Health $health): bool => $health->severity() < 2)->count(), 'label' => $healthy, 'tone' => 'ok'],
        ];
    }
}
