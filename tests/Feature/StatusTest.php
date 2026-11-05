<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\ProcessCheck;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

beforeEach(function () {
    config(['vitals.status_token' => 'secret']);
    app(Dispatcher::class)->dispatchDue(CarbonImmutable::now()->subMinutes(10));
});

function runCentral(Result $result): void
{
    Vitals::central([(new FixedCheck($result))->withKey('database')]);
    app(ProcessCheck::class)->handle(Vitals::find(Scope::Central, 'database'));
}

it('requires the gate or a valid token', function () {
    $this->getJson('/vitals/status.json')->assertForbidden();
    $this->getJson('/vitals/status.json', ['X-Vitals-Token' => 'wrong'])->assertForbidden();
});

it('returns 200 when everything is healthy', function () {
    Cache::forever(Dispatcher::HEARTBEAT_KEY, now()->getTimestamp());
    runCentral(Result::ok());

    $this->getJson('/vitals/status.json', ['X-Vitals-Token' => 'secret'])
        ->assertOk()
        ->assertJson(['status' => 'ok', 'checks' => 1, 'problems' => [], 'dispatcher' => ['stale' => false]]);
});

it('returns 503 when a check fails', function () {
    Cache::forever(Dispatcher::HEARTBEAT_KEY, now()->getTimestamp());
    runCentral(Result::failed('Down'));

    $this->getJson('/vitals/status.json', ['X-Vitals-Token' => 'secret'])
        ->assertStatus(503)
        ->assertJsonPath('status', 'failing')
        ->assertJsonPath('problems.0.check', 'database')
        ->assertJsonPath('problems.0.summary', 'Down');
});

it('returns 503 when the dispatcher stopped', function () {
    runCentral(Result::ok());

    $this->getJson('/vitals/status.json', ['X-Vitals-Token' => 'secret'])
        ->assertStatus(503)
        ->assertJsonPath('dispatcher.stale', true);
});
