<?php

namespace NetOs\Vitals\Running;

use Carbon\CarbonImmutable;
use Closure;
use NetOs\Vitals\Check;
use NetOs\Vitals\Exceptions\CheckTimedOut;
use NetOs\Vitals\Result;
use Throwable;

/**
 * Runs a single check. A check that throws or exceeds its timeout becomes "crashed"; it never throws.
 */
class CheckRunner
{
    public function run(Check $check): CheckRun
    {
        $startedAt = CarbonImmutable::now();
        $start = hrtime(true);

        try {
            $result = $this->withTimeout($check->timeoutSeconds(), fn (): Result => $check->run());
        } catch (Throwable $exception) {
            if (! $exception instanceof CheckTimedOut) {
                report($exception);
            }

            $result = Result::crashed($exception->getMessage() !== '' ? $exception->getMessage() : $exception::class);
        }

        return new CheckRun(
            result: $result,
            startedAt: $startedAt,
            finishedAt: CarbonImmutable::now(),
            durationMs: (int) round((hrtime(true) - $start) / 1_000_000),
        );
    }

    /**
     * Uses SIGALRM where pcntl is available, and restores an alarm that was already set (such as
     * the queue worker's job timeout) afterwards.
     *
     * @param  Closure(): Result  $callback
     */
    private function withTimeout(int $seconds, Closure $callback): Result
    {
        if ($seconds <= 0 || ! function_exists('pcntl_alarm') || ! function_exists('pcntl_signal')) {
            return $callback();
        }

        $previousHandler = pcntl_signal_get_handler(SIGALRM);
        $previousAsync = pcntl_async_signals(true);
        $previousAlarm = pcntl_alarm(0);
        $start = time();

        pcntl_signal(SIGALRM, static function () use ($seconds): never {
            throw CheckTimedOut::after($seconds);
        });

        pcntl_alarm($seconds);

        try {
            return $callback();
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previousHandler);
            pcntl_async_signals($previousAsync);

            if ($previousAlarm > 0) {
                pcntl_alarm(max(1, $previousAlarm - (time() - $start)));
            }
        }
    }
}
