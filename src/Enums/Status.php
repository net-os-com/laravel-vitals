<?php

namespace NetOs\Vitals\Enums;

enum Status: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failed = 'failed';
    case Crashed = 'crashed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Warning => 'Warning',
            self::Failed => 'Failed',
            self::Crashed => 'Crashed',
            self::Skipped => 'Skipped',
        };
    }

    public function isFailing(): bool
    {
        return $this === self::Failed || $this === self::Crashed;
    }

    public function isHealthy(): bool
    {
        return $this === self::Ok || $this === self::Skipped;
    }
}
