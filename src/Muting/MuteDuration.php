<?php

namespace NetOs\Vitals\Muting;

use Carbon\CarbonImmutable;

enum MuteDuration: string
{
    case OneHour = '1h';
    case FourHours = '4h';
    case OneDay = '1d';
    case OneWeek = '1w';
    case UntilRecovered = 'recovered';
    case UntilUnmuted = 'unmuted';

    public function label(): string
    {
        return match ($this) {
            self::OneHour => '1 hour',
            self::FourHours => '4 hours',
            self::OneDay => '1 day',
            self::OneWeek => '1 week',
            self::UntilRecovered => 'Until it recovers',
            self::UntilUnmuted => 'Until I unmute it',
        };
    }

    public function until(CarbonImmutable $now): ?CarbonImmutable
    {
        return match ($this) {
            self::OneHour => $now->addHour(),
            self::FourHours => $now->addHours(4),
            self::OneDay => $now->addDay(),
            self::OneWeek => $now->addWeek(),
            default => null,
        };
    }
}
