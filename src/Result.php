<?php

namespace NetOs\Vitals;

use NetOs\Vitals\Enums\Status;

final class Result
{
    /**
     * @param  array<string, scalar|null>  $meta
     */
    private function __construct(
        public readonly Status $status,
        public readonly string $summary,
        public readonly array $meta = [],
    ) {}

    public static function ok(string $summary = ''): self
    {
        return new self(Status::Ok, $summary);
    }

    public static function warning(string $summary): self
    {
        return new self(Status::Warning, $summary);
    }

    public static function failed(string $summary): self
    {
        return new self(Status::Failed, $summary);
    }

    public static function skipped(string $reason): self
    {
        return new self(Status::Skipped, $reason);
    }

    /**
     * Used by the runner when a check throws or times out. Checks themselves return failed().
     */
    public static function crashed(string $summary): self
    {
        return new self(Status::Crashed, $summary);
    }

    /**
     * @param  array<string, scalar|null>  $meta
     */
    public function meta(array $meta): self
    {
        return new self($this->status, $this->summary, [...$this->meta, ...$meta]);
    }
}
