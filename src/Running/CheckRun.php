<?php

namespace NetOs\Vitals\Running;

use Carbon\CarbonImmutable;
use NetOs\Vitals\Result;

final readonly class CheckRun
{
    public function __construct(
        public Result $result,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $finishedAt,
        public int $durationMs,
    ) {}
}
