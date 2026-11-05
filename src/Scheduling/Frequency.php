<?php

namespace NetOs\Vitals\Scheduling;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Cron\CronExpression;

final readonly class Frequency
{
    private CronExpression $cron;

    public function __construct(public string $expression)
    {
        $this->cron = new CronExpression($expression);
    }

    /**
     * Whether the check is due at this minute. The jitter seed (a tenant key) shifts the schedule by a
     * stable offset within the interval, so tenants do not all run at the same minute.
     */
    public function isDue(CarbonInterface $now, ?string $jitterSeed = null): bool
    {
        $moment = CarbonImmutable::instance($now)->startOfMinute()->subMinutes($this->jitterMinutes($jitterSeed));

        return $this->cron->isDue($moment->toDateTimeString(), $moment->getTimezone()->getName());
    }

    public function intervalMinutes(): int
    {
        $from = new \DateTimeImmutable('2026-01-05 12:00:00');
        $next = $this->cron->getNextRunDate($from, 0, true);
        $after = $this->cron->getNextRunDate($next, 1, true);

        return max(1, intdiv($after->getTimestamp() - $next->getTimestamp(), 60));
    }

    public function jitterMinutes(?string $seed): int
    {
        if ($seed === null) {
            return 0;
        }

        return crc32($seed) % min($this->intervalMinutes(), 15);
    }

    /**
     * A check is stale when it has not reported for twice its interval plus five minutes.
     */
    public function staleAfterMinutes(): int
    {
        return $this->intervalMinutes() * 2 + 5;
    }

    public function label(): string
    {
        $parts = explode(' ', $this->expression);

        return match (true) {
            $this->expression === '* * * * *' => 'Every minute',
            (bool) preg_match('/^\*\/(\d+) \* \* \* \*$/', $this->expression, $match) => "Every {$match[1]} minutes",
            (bool) preg_match('/^(\d+) \* \* \* \*$/', $this->expression) => 'Hourly',
            (bool) preg_match('/^(\d+) (\d+) \* \* \*$/', $this->expression) => sprintf('Daily at %02d:%02d', $parts[1], $parts[0]),
            default => $this->expression,
        };
    }
}
