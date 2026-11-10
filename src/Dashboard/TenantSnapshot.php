<?php

namespace NetOs\Vitals\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use NetOs\Vitals\Tenancy\TenantReference;

final readonly class TenantSnapshot
{
    /**
     * @param  Collection<int, CheckSnapshot>  $checks  in registration order, including the ones that do not apply
     */
    public function __construct(
        public TenantReference $tenant,
        public Collection $checks,
    ) {}

    /**
     * @return Collection<int, CheckSnapshot>
     */
    public function applicable(): Collection
    {
        return $this->checks->reject(fn (CheckSnapshot $check): bool => $check->health === Health::NotApplicable)->values();
    }

    /**
     * Problems first (by severity, then registration order), not applicable last.
     *
     * @return Collection<int, CheckSnapshot>
     */
    public function sorted(): Collection
    {
        return $this->checks
            ->map(fn (CheckSnapshot $check, int $index): array => [$check, $index])
            ->sort(fn (array $a, array $b): int => [$b[0]->attentionHealth()->severity(), $b[0]->health->severity(), $a[1]] <=> [$a[0]->attentionHealth()->severity(), $a[0]->health->severity(), $b[1]])
            ->map(fn (array $pair): CheckSnapshot => $pair[0])
            ->values();
    }

    /**
     * @return Collection<int, CheckSnapshot>
     */
    public function problems(): Collection
    {
        return $this->sorted()->filter(fn (CheckSnapshot $check): bool => $check->needsAttention())->values();
    }

    public function health(): Health
    {
        return $this->applicable()
            ->map(fn (CheckSnapshot $check): Health => $check->attentionHealth())
            ->sortByDesc(fn (Health $health): int => $health->severity())
            ->first() ?? Health::Pending;
    }

    public function isUnreachable(): bool
    {
        $applicable = $this->applicable();

        return $applicable->isNotEmpty()
            && $applicable->every(fn (CheckSnapshot $check): bool => $check->couldNotInitializeTenant());
    }

    public function count(Health ...$health): int
    {
        return $this->applicable()->filter(fn (CheckSnapshot $check): bool => in_array($check->attentionHealth(), $health, true))->count();
    }

    public function muted(): int
    {
        return $this->applicable()->filter(fn (CheckSnapshot $check): bool => $check->isMutedProblem())->count();
    }

    public function failing(): int
    {
        return $this->count(Health::Failed, Health::Crashed);
    }

    public function lastRunAt(): ?CarbonImmutable
    {
        return $this->applicable()
            ->map(fn (CheckSnapshot $check): ?CarbonImmutable => $check->lastRunAt())
            ->filter()
            ->sortDesc()
            ->first();
    }

    public function needsAttention(): bool
    {
        return $this->health()->needsAttention();
    }

    public function statusText(): string
    {
        if ($this->isUnreachable()) {
            return $this->needsAttention() ? 'Unreachable' : 'Unreachable, muted';
        }

        $parts = array_filter([
            $this->failing() > 0 ? $this->failing().' failing' : null,
            $this->count(Health::Warning) > 0 ? $this->count(Health::Warning).' '.str('warning')->plural($this->count(Health::Warning)) : null,
            $this->count(Health::Stale) > 0 ? $this->count(Health::Stale).' stale' : null,
            $this->muted() > 0 ? $this->muted().' muted' : null,
        ]);

        if ($parts !== [] && ! $this->needsAttention()) {
            return 'All OK, '.implode(', ', $parts);
        }

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return $this->health() === Health::Pending ? 'Not run yet' : 'All OK';
    }

    public function badgeText(): string
    {
        return match (true) {
            $this->isUnreachable() => 'Unreachable',
            $this->failing() > 0 => $this->failing().' failing',
            $this->count(Health::Warning) > 1 => $this->count(Health::Warning).' warnings',
            default => $this->health()->label(),
        };
    }
}
