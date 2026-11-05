<?php

namespace NetOs\Vitals\Checks;

use Carbon\CarbonImmutable;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;
use NetOs\Vitals\Scheduling\Dispatcher;

/**
 * vitals:dispatch runs every minute from the scheduler, so its heartbeat is the scheduler's heartbeat.
 */
class SchedulerHeartbeatCheck extends Check
{
    protected int $failAfterMinutes = 3;

    public function failAfterMinutes(int $minutes): static
    {
        $this->failAfterMinutes = $minutes;

        return $this;
    }

    public function run(): Result
    {
        $heartbeat = app(Dispatcher::class)->lastHeartbeat();

        if ($heartbeat === null) {
            return Result::failed('The scheduler has never run vitals:dispatch');
        }

        $seconds = (int) $heartbeat->diffInSeconds(CarbonImmutable::now());
        $ago = $seconds < 60 ? "{$seconds} seconds ago" : intdiv($seconds, 60).' min ago';
        $meta = ['last_tick' => $heartbeat->toDateTimeString()];

        if ($seconds > $this->failAfterMinutes * 60) {
            return Result::failed("Last tick {$ago}")->meta($meta);
        }

        return Result::ok("Last tick {$ago}")->meta($meta);
    }
}
