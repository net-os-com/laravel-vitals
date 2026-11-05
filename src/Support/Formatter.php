<?php

namespace NetOs\Vitals\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dates follow "Wed, 13 Aug, 09:00", with Today, Yesterday and Tomorrow, and June and July in full.
 */
final class Formatter
{
    public static function timezone(): string
    {
        $timezone = config('vitals.dashboard.timezone') ?? config('app.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    public static function local(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::timezone());
    }

    public static function dateTime(?CarbonInterface $moment): string
    {
        if ($moment === null) {
            return '—';
        }

        return self::day($moment).', '.self::local($moment)->format('H:i');
    }

    public static function day(CarbonInterface $moment): string
    {
        $moment = self::local($moment);
        $today = CarbonImmutable::now(self::timezone())->startOfDay();

        return match (true) {
            $moment->isSameDay($today) => 'Today',
            $moment->isSameDay($today->subDay()) => 'Yesterday',
            $moment->isSameDay($today->addDay()) => 'Tomorrow',
            default => $moment->format('D, j ').self::month($moment).($moment->year === $today->year ? '' : ' '.$moment->year),
        };
    }

    public static function time(CarbonInterface $moment): string
    {
        return self::local($moment)->format('H:i');
    }

    public static function ago(?CarbonInterface $moment, ?CarbonInterface $now = null): string
    {
        if ($moment === null) {
            return 'Never';
        }

        $seconds = max(0, (int) $moment->diffInSeconds($now ?? CarbonImmutable::now(), true));

        return match (true) {
            $seconds < 10 => 'just now',
            $seconds < 60 => "{$seconds} seconds ago",
            $seconds < 3600 => intdiv($seconds, 60).' min ago',
            $seconds < 86400 => intdiv($seconds, 3600).' h ago',
            default => self::dateTime($moment),
        };
    }

    public static function duration(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => "{$seconds} s",
            $seconds < 3600 => intdiv($seconds, 60).' min',
            $seconds < 86400 * 2 => intdiv($seconds, 3600).' h',
            default => intdiv($seconds, 86400).' days',
        };
    }

    public static function milliseconds(int|float|null $milliseconds): string
    {
        if ($milliseconds === null) {
            return '—';
        }

        return $milliseconds < 1000
            ? number_format($milliseconds).' ms'
            : number_format($milliseconds / 1000, 1).' s';
    }

    public static function exactMilliseconds(int $milliseconds): string
    {
        return number_format($milliseconds).' ms';
    }

    private static function month(CarbonInterface $moment): string
    {
        return in_array($moment->month, [6, 7], true) ? $moment->format('F') : $moment->format('M');
    }
}
