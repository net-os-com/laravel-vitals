<?php

use Carbon\CarbonImmutable;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Models\Mute;
use NetOs\Vitals\Result;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-10 09:00:00');
    fakeTenants(['acme']);

    Vitals::central([(new FixedCheck(Result::ok()))->withKey('database'), (new FixedCheck(Result::ok()))->withKey('shared')]);
    Vitals::tenant([(new FixedCheck(Result::ok()))->withKey('auth0'), (new FixedCheck(Result::ok()))->withKey('shared')]);
});

it('mutes everything for a while, for a deploy', function () {
    $this->artisan('vitals:mute --all --for=15m --reason=Deploy')->assertSuccessful();

    $this->assertDatabaseHas('vitals_mutes', ['scope' => 'central', 'check_key' => null, 'tenant_key' => null, 'until' => '2026-11-10 09:15:00', 'reason' => 'Deploy']);
    $this->assertDatabaseHas('vitals_mutes', ['scope' => 'tenant', 'check_key' => null, 'tenant_key' => null, 'until' => '2026-11-10 09:15:00']);

    $this->artisan('vitals:unmute --reason=Deploy')->expectsOutputToContain('Lifted 2 mutes')->assertSuccessful();
    $this->assertDatabaseCount('vitals_mutes', 0);
});

it('mutes a single check, a tenant or a central check with the same key', function (string $command, array $row) {
    $this->artisan($command)->assertSuccessful();

    $this->assertDatabaseHas('vitals_mutes', $row);
})->with([
    'central check' => ['vitals:mute database --for=2h', ['scope' => 'central', 'check_key' => 'database', 'until' => '2026-11-10 11:00:00']],
    'tenant check for a tenant' => ['vitals:mute auth0 --tenant=acme --until-recovered', ['scope' => 'tenant', 'check_key' => 'auth0', 'tenant_key' => 'acme', 'until_recovered' => true]],
    'tenant check for all tenants' => ['vitals:mute auth0 --for=1d', ['scope' => 'tenant', 'check_key' => 'auth0', 'tenant_key' => null, 'until' => '2026-11-11 09:00:00']],
    'whole tenant' => ['vitals:mute --tenant=acme', ['scope' => 'tenant', 'check_key' => null, 'tenant_key' => 'acme', 'until' => null]],
    'tenant check over a central one' => ['vitals:mute shared', ['scope' => 'tenant', 'check_key' => 'shared']],
    'central check with --central' => ['vitals:mute shared --central', ['scope' => 'central', 'check_key' => 'shared']],
]);

it('rejects unknown checks, tenants and durations', function (string $command) {
    $this->artisan($command)->assertFailed();

    $this->assertDatabaseCount('vitals_mutes', 0);
})->with([
    'vitals:mute nope',
    'vitals:mute --tenant=nope',
    'vitals:mute --all --for=soon',
    'vitals:mute',
]);

it('lifts mutes by id or all at once', function () {
    $this->artisan('vitals:mute database');
    $this->artisan('vitals:mute auth0');
    $id = Mute::query()->value('id');

    $this->artisan("vitals:unmute {$id}")->expectsOutputToContain('Lifted 1 mute')->assertSuccessful();
    $this->artisan('vitals:unmute --all')->expectsOutputToContain('Lifted 1 mute')->assertSuccessful();
    $this->artisan('vitals:unmute')->assertFailed();
});
