<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Events\CheckFinished;
use NetOs\Vitals\Events\CheckStatusChanged;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\ProcessCheck;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

function processWith(Result ...$results): void
{
    $queue = $results;
    Vitals::central([(new FixedCheck(function () use (&$queue): Result {
        return array_shift($queue);
    }))->withKey('flaky')]);

    $check = Vitals::find(Scope::Central, 'flaky');

    foreach ($results as $index => $result) {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 09:00')->addMinutes($index * 5));
        app(ProcessCheck::class)->handle($check);
    }
}

it('stores every run and the latest state', function () {
    processWith(Result::ok('Fine')->meta(['ms' => 3]));

    $this->assertDatabaseHas('vitals_results', [
        'check_key' => 'flaky',
        'tenant_key' => null,
        'status' => 'ok',
        'summary' => 'Fine',
        'meta' => json_encode(['ms' => 3]),
    ]);
    $this->assertDatabaseHas('vitals_states', [
        'check_key' => 'flaky',
        'status' => 'ok',
        'consecutive_failures' => 0,
        'last_success_at' => '2026-11-05 09:00:00',
    ]);
});

it('counts consecutive failures and keeps the moment the status changed', function () {
    processWith(Result::ok(), Result::failed('Down'), Result::failed('Still down'));

    $this->assertDatabaseCount('vitals_results', 3);
    $this->assertDatabaseCount('vitals_states', 1);
    $this->assertDatabaseHas('vitals_states', [
        'status' => 'failed',
        'summary' => 'Still down',
        'consecutive_failures' => 2,
        'status_since' => '2026-11-05 09:05:00',
        'last_run_at' => '2026-11-05 09:10:00',
        'last_success_at' => '2026-11-05 09:00:00',
    ]);
});

it('resets the failure count after a success', function () {
    processWith(Result::failed('Down'), Result::ok());

    $this->assertDatabaseHas('vitals_states', ['status' => 'ok', 'consecutive_failures' => 0]);
});

it('dispatches CheckFinished for every run and CheckStatusChanged on a change', function () {
    Event::fake([CheckFinished::class, CheckStatusChanged::class]);

    processWith(Result::ok(), Result::ok(), Result::warning('Slow'));

    Event::assertDispatchedTimes(CheckFinished::class, 3);
    Event::assertDispatchedTimes(CheckStatusChanged::class, 2);
    Event::assertDispatched(CheckStatusChanged::class, fn (CheckStatusChanged $event) => $event->from === null && $event->to === Status::Ok);
    Event::assertDispatched(CheckStatusChanged::class, fn (CheckStatusChanged $event) => $event->from === Status::Ok && $event->to === Status::Warning);
});
