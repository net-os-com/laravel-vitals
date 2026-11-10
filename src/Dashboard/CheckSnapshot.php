<?php

namespace NetOs\Vitals\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Exceptions\TenantNotInitialized;
use NetOs\Vitals\Models\CheckState;
use NetOs\Vitals\Models\Mute;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Tenancy\TenantReference;

final readonly class CheckSnapshot
{
    /**
     * @param  Collection<int, Status>  $recent  newest first
     */
    public function __construct(
        public RegisteredCheck $check,
        public ?TenantReference $tenant,
        public ?CheckState $state,
        public Health $health,
        public Collection $recent,
        public ?Mute $mute = null,
    ) {}

    /**
     * @param  Collection<int, Status>|null  $recent  newest first
     */
    public static function make(
        RegisteredCheck $check,
        ?TenantReference $tenant,
        ?CheckState $state,
        CarbonImmutable $now,
        ?Collection $recent = null,
        ?Mute $mute = null,
    ): self {
        $health = match (true) {
            $tenant !== null && ! $check->check->appliesTo($tenant) => Health::NotApplicable,
            $state === null => Health::Pending,
            $state->last_run_at->addMinutes($check->frequency->staleAfterMinutes())->lt($now) => Health::Stale,
            default => Health::fromStatus($state->status),
        };

        return new self($check, $tenant, $state, $health, $recent ?? new Collection, $mute);
    }

    public function isMuted(): bool
    {
        return $this->mute !== null;
    }

    /**
     * Whether this check has a problem that nobody muted.
     */
    public function needsAttention(): bool
    {
        return $this->health->needsAttention() && $this->mute === null;
    }

    /**
     * The health for counters and status.json: a muted problem counts as OK.
     */
    public function attentionHealth(): Health
    {
        return $this->mute !== null && $this->health->needsAttention() ? Health::Ok : $this->health;
    }

    public function isMutedProblem(): bool
    {
        return $this->mute !== null && $this->health->needsAttention();
    }

    public function summary(): string
    {
        return match ($this->health) {
            Health::NotApplicable => 'This check does not apply to this tenant.',
            Health::Pending => 'This check has not run yet.',
            default => $this->state->summary ?? '',
        };
    }

    public function lastRunAt(): ?CarbonImmutable
    {
        return $this->state?->last_run_at;
    }

    public function couldNotInitializeTenant(): bool
    {
        return $this->state !== null
            && $this->health->isFailing()
            && str_starts_with($this->state->summary, TenantNotInitialized::PREFIX);
    }

    public function minutesWithoutResult(CarbonImmutable $now): int
    {
        return $this->state === null ? 0 : (int) $this->state->last_run_at->diffInMinutes($now);
    }

    /**
     * The last runs, oldest first, padded to the given length with null for missing runs.
     *
     * @return array<int, Status|null>
     */
    public function strip(int $length = 24): array
    {
        $runs = $this->recent->take($length)->reverse()->values()->all();

        return [...array_fill(0, $length - count($runs), null), ...$runs];
    }
}
