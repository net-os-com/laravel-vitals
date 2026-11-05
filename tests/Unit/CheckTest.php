<?php

use NetOs\Vitals\Checks\DatabaseCheck;
use NetOs\Vitals\Checks\TenantDatabaseCheck;
use NetOs\Vitals\Result;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

it('derives the key and label from the class name', function () {
    $check = DatabaseCheck::new();

    expect($check->key())->toBe('database')
        ->and($check->label())->toBe('Database')
        ->and(TenantDatabaseCheck::new()->key())->toBe('tenant-database');
});

it('can override the key, label and timeout', function () {
    $check = DatabaseCheck::new()->withKey('replica')->withLabel('Read replica')->timeout(5);

    expect($check->key())->toBe('replica')
        ->and($check->label())->toBe('Read replica')
        ->and($check->timeoutSeconds())->toBe(5);
});

it('applies to every tenant unless onlyFor is given', function () {
    $acme = new TenantReference('acme', 'Acme');
    $globex = new TenantReference('globex', 'Globex');
    $check = new FixedCheck(Result::ok());

    expect($check->appliesTo($acme))->toBeTrue();

    $check->onlyFor(fn (TenantReference $tenant): bool => $tenant->key === 'acme');

    expect($check->appliesTo($acme))->toBeTrue()
        ->and($check->appliesTo($globex))->toBeFalse()
        ->and($check->hasTenantCondition())->toBeTrue();
});

it('stores frequencies as cron expressions', function () {
    $check = new FixedCheck(Result::ok());

    expect($check->expression())->toBeNull()
        ->and($check->hourly()->expression())->toBe('0 * * * *')
        ->and($check->dailyAt('06:30')->expression())->toBe('30 6 * * *')
        ->and($check->everyFifteenMinutes()->expression())->toBe('*/15 * * * *');
});

it('creates immutable results with meta', function () {
    $result = Result::warning('Almost full');
    $withMeta = $result->meta(['used' => 81])->meta(['limit' => 100]);

    expect($result->meta)->toBe([])
        ->and($withMeta->meta)->toBe(['used' => 81, 'limit' => 100])
        ->and($withMeta->summary)->toBe('Almost full')
        ->and($withMeta->status->value)->toBe('warning');
});
