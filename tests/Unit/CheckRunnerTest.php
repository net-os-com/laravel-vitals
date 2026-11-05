<?php

use Illuminate\Support\Facades\Exceptions;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\CheckRunner;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

it('returns the result of the check with timing', function () {
    $run = (new CheckRunner)->run(new FixedCheck(Result::ok('Fine')));

    expect($run->result->status)->toBe(Status::Ok)
        ->and($run->result->summary)->toBe('Fine')
        ->and($run->durationMs)->toBeGreaterThanOrEqual(0)
        ->and($run->finishedAt->gte($run->startedAt))->toBeTrue();
});

it('turns an exception into a crashed result and reports it', function () {
    Exceptions::fake();

    $run = (new CheckRunner)->run(new FixedCheck(fn () => throw new RuntimeException('Connection refused')));

    expect($run->result->status)->toBe(Status::Crashed)
        ->and($run->result->summary)->toBe('Connection refused');

    Exceptions::assertReported(RuntimeException::class);
});

it('crashes a check that exceeds its timeout', function () {
    $run = (new CheckRunner)->run((new FixedCheck(function (): Result {
        sleep(3);

        return Result::ok();
    }))->timeout(1));

    expect($run->result->status)->toBe(Status::Crashed)
        ->and($run->result->summary)->toBe('Check timed out after 1 seconds')
        ->and($run->durationMs)->toBeLessThan(2500);
})->skip(! function_exists('pcntl_alarm'), 'pcntl is not available');
