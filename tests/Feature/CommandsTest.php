<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Jobs\RunCheckJob;
use NetOs\Vitals\Jobs\RunTenantChecksJob;
use NetOs\Vitals\Result;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

beforeEach(function () {
    Vitals::central([
        (new FixedCheck(Result::ok('Fine')))->withKey('every-minute')->everyMinute(),
        (new FixedCheck(Result::failed('Broken')))->withKey('hourly')->hourly(),
    ]);
});

it('dispatches the checks that are due and records the heartbeat', function () {
    Queue::fake();
    fakeTenants(['acme']);
    Vitals::tenant([(new FixedCheck(Result::ok()))->withKey('tenant-check')->everyMinute()]);
    CarbonImmutable::setTestNow('2026-11-05 10:07:00');

    $this->artisan('vitals:dispatch')->assertSuccessful();

    Queue::assertPushed(RunCheckJob::class, 1);
    Queue::assertPushed(RunCheckJob::class, fn (RunCheckJob $job) => $job->checkKey === 'every-minute');
    Queue::assertPushed(RunTenantChecksJob::class, fn (RunTenantChecksJob $job) => $job->tenantKey === 'acme' && $job->checkKeys === ['tenant-check']);
    expect(Cache::get(Dispatcher::HEARTBEAT_KEY))->toBe(CarbonImmutable::now()->getTimestamp());
});

it('dispatches every check with --all', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-11-05 10:07:00');

    $this->artisan('vitals:dispatch --all')->assertSuccessful();

    Queue::assertPushed(RunCheckJob::class, 2);
});

it('puts the jobs on the configured queue', function () {
    Queue::fake();
    config(['vitals.queue' => 'vitals']);

    $this->artisan('vitals:dispatch --all');

    Queue::assertPushedOn('vitals', RunCheckJob::class);
});

it('runs checks synchronously and fails when one fails', function () {
    $this->artisan('vitals:run')
        ->expectsOutputToContain('Broken')
        ->assertFailed();

    $this->assertDatabaseCount('vitals_results', 2);
});

it('runs a single check by key', function () {
    $this->artisan('vitals:run every-minute')->assertSuccessful();

    $this->assertDatabaseHas('vitals_results', ['check_key' => 'every-minute']);
    $this->assertDatabaseMissing('vitals_results', ['check_key' => 'hourly']);
});

it('runs the checks of one tenant', function () {
    fakeTenants(['acme', 'globex']);
    Vitals::tenant([(new FixedCheck(Result::ok()))->withKey('tenant-check')]);

    $this->artisan('vitals:run --tenant=globex')->assertSuccessful();

    $this->assertDatabaseHas('vitals_results', ['check_key' => 'tenant-check', 'tenant_key' => 'globex']);
    $this->assertDatabaseMissing('vitals_results', ['tenant_key' => 'acme']);
    $this->assertDatabaseMissing('vitals_results', ['check_key' => 'every-minute']);
});

it('lists the registered checks', function () {
    $this->artisan('vitals:list')
        ->expectsTable(['Key', 'Label', 'Scope', 'Schedule', 'Timeout', 'Only for'], [
            ['every-minute', 'Every minute', 'central', 'Every minute', '30s', '—'],
            ['hourly', 'Hourly', 'central', 'Hourly', '30s', '—'],
        ])
        ->assertSuccessful();
});

it('prunes results older than the retention period', function () {
    $row = ['check_key' => 'hourly', 'status' => 'ok', 'summary' => '', 'duration_ms' => 1];

    DB::table('vitals_results')->insert([
        [...$row, 'started_at' => now()->subDays(15), 'finished_at' => now()->subDays(15)],
        [...$row, 'started_at' => now()->subDays(13), 'finished_at' => now()->subDays(13)],
    ]);

    $this->artisan('vitals:prune')->assertSuccessful();

    $this->assertDatabaseCount('vitals_results', 1);
});
