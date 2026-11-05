<?php

namespace NetOs\Vitals\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Models\CheckResult;
use NetOs\Vitals\Support\Formatter;

final readonly class CheckDetail
{
    public const array RANGES = ['24h' => '24 hours', '7d' => '7 days', '14d' => '14 days'];

    /**
     * @param  Collection<int, CheckResult>  $history  oldest first
     * @param  LengthAwarePaginator<int, CheckResult>  $runs
     */
    public function __construct(
        public CheckSnapshot $snapshot,
        public string $range,
        public CarbonImmutable $since,
        public CarbonImmutable $now,
        public Collection $history,
        public LengthAwarePaginator $runs,
        public ?CheckResult $latest,
    ) {}

    public static function since(string $range, CarbonImmutable $now): CarbonImmutable
    {
        $local = Formatter::local($now);

        return match ($range) {
            '24h' => $now->subDay(),
            '14d' => $local->startOfDay()->subDays(13),
            default => $local->startOfDay()->subDays(6),
        };
    }

    public function okPercentage(): ?float
    {
        if ($this->history->isEmpty()) {
            return null;
        }

        $healthy = $this->history->filter(fn (CheckResult $result): bool => $result->status->isHealthy())->count();

        return round($healthy / $this->history->count() * 100, 1);
    }

    public function medianDuration(): ?float
    {
        $median = $this->history->pluck('duration_ms')->median();

        return is_numeric($median) ? (float) $median : null;
    }

    public function secondsSinceLastSuccess(): ?int
    {
        $lastSuccess = $this->snapshot->state?->last_success_at;

        return $lastSuccess === null ? null : (int) $lastSuccess->diffInSeconds($this->now, true);
    }

    /**
     * Rows are days, columns hours; each cell holds the worst status of that hour.
     *
     * @return array<int, array{day: string, cells: array<int, array{tone: string, title: string}>}>
     */
    public function heatmap(): array
    {
        $buckets = [];

        foreach ($this->history as $result) {
            $key = Formatter::local($result->finished_at)->format('Y-m-d H');

            if (! isset($buckets[$key]) || self::rank($result->status) > self::rank($buckets[$key])) {
                $buckets[$key] = $result->status;
            }
        }

        $rows = [];
        $day = Formatter::local($this->since)->startOfDay();
        $today = Formatter::local($this->now)->startOfDay();

        while ($day->lte($today)) {
            $cells = [];

            for ($hour = 0; $hour < 24; $hour++) {
                $status = $buckets[$day->format('Y-m-d').' '.sprintf('%02d', $hour)] ?? null;
                $label = Formatter::day($day).' '.sprintf('%02d:00', $hour);

                $cells[] = [
                    'tone' => match (true) {
                        $status === null => 'n',
                        $status->isFailing() => 'f',
                        $status === Status::Warning => 'w',
                        default => '',
                    },
                    'title' => $status === null ? "{$label} · no runs" : "{$label} · {$status->label()}",
                ];
            }

            $rows[] = ['day' => Formatter::day($day), 'cells' => $cells];
            $day = $day->addDay();
        }

        return $rows;
    }

    /**
     * @return array<int, array{from: Status, to: Status, at: CarbonImmutable}>
     */
    public function statusChanges(int $limit = 8): array
    {
        $changes = [];
        $previous = null;

        foreach ($this->history as $result) {
            if ($previous !== null && $previous !== $result->status) {
                $changes[] = ['from' => $previous, 'to' => $result->status, 'at' => $result->finished_at];
            }

            $previous = $result->status;
        }

        return array_slice(array_reverse($changes), 0, $limit);
    }

    private static function rank(Status $status): int
    {
        return match ($status) {
            Status::Failed, Status::Crashed => 2,
            Status::Warning => 1,
            default => 0,
        };
    }
}
