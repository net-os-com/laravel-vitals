<?php

namespace NetOs\Vitals\Dashboard;

use NetOs\Vitals\Enums\Status;

/**
 * What the dashboard shows for a check: the stored status, or a derived state.
 */
enum Health: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failed = 'failed';
    case Crashed = 'crashed';
    case Skipped = 'skipped';
    case Stale = 'stale';
    case Pending = 'pending';
    case NotApplicable = 'not-applicable';

    public static function fromStatus(Status $status): self
    {
        return self::from($status->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Warning => 'Warning',
            self::Failed => 'Failed',
            self::Crashed => 'Crashed',
            self::Skipped => 'Skipped',
            self::Stale => 'Stale',
            self::Pending => 'Not run yet',
            self::NotApplicable => 'Not applicable',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Ok => 'ok',
            self::Warning => 'warn',
            self::Failed, self::Crashed => 'fail',
            default => 'idle',
        };
    }

    public function severity(): int
    {
        return match ($this) {
            self::Failed, self::Crashed => 4,
            self::Warning => 3,
            self::Stale => 2,
            self::Pending => 1,
            self::Ok, self::Skipped => 0,
            self::NotApplicable => -1,
        };
    }

    public function isFailing(): bool
    {
        return $this === self::Failed || $this === self::Crashed;
    }

    public function needsAttention(): bool
    {
        return $this->severity() >= 2;
    }
}
