<?php

namespace NetOs\Vitals\Dashboard;

use NetOs\Vitals\Enums\Status;

/**
 * A check flaps when it keeps switching between healthy and unhealthy, which usually means a threshold
 * that is too tight rather than a real outage.
 */
final class Flapping
{
    public const int WINDOW = 12;

    public const int THRESHOLD = 4;

    /**
     * @param  iterable<Status>  $statuses  the latest runs, in any consistent order
     */
    public static function changes(iterable $statuses): int
    {
        $changes = 0;
        $previous = null;
        $seen = 0;

        foreach ($statuses as $status) {
            if (++$seen > self::WINDOW) {
                break;
            }

            if ($previous !== null && $previous !== $status->isHealthy()) {
                $changes++;
            }

            $previous = $status->isHealthy();
        }

        return $changes;
    }

    /**
     * @param  iterable<Status>  $statuses
     */
    public static function detect(iterable $statuses): bool
    {
        return self::changes($statuses) >= self::THRESHOLD;
    }
}
