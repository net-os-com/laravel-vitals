<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use NetOs\Vitals\Checks\DatabaseCheck;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Exceptions\DuplicateCheckKey;
use NetOs\Vitals\Facades\Vitals;

it('registers central and tenant checks with the scope default frequency', function () {
    Vitals::central([DatabaseCheck::new()]);
    Vitals::tenant([DatabaseCheck::new()->hourly()]);

    $central = Vitals::find(Scope::Central, 'database');
    $tenant = Vitals::find(Scope::Tenant, 'database');

    expect($central->frequency->expression)->toBe('*/5 * * * *')
        ->and($tenant->frequency->expression)->toBe('0 * * * *')
        ->and(Vitals::checks(Scope::Central))->toHaveCount(1);
});

it('rejects a duplicate key within a scope', function () {
    Vitals::central([DatabaseCheck::new(), DatabaseCheck::new()]);
})->throws(DuplicateCheckKey::class);

it('only allows the local environment without a gate or callback', function () {
    $request = Request::create('/vitals');

    expect(Vitals::check($request))->toBeFalse();

    app()->detectEnvironment(fn () => 'local');

    expect(Vitals::check($request))->toBeTrue();
});

it('uses the viewVitals gate when it is defined', function () {
    Gate::define('viewVitals', fn ($user = null) => true);

    expect(Vitals::check(Request::create('/vitals')))->toBeTrue();
});

it('prefers the auth callback over the gate', function () {
    Gate::define('viewVitals', fn ($user = null) => true);
    Vitals::auth(fn (Request $request): bool => $request->ip() === '10.0.0.1');

    expect(Vitals::check(Request::create('/vitals')))->toBeFalse()
        ->and(Vitals::check(Request::create('/vitals', server: ['REMOTE_ADDR' => '10.0.0.1'])))->toBeTrue();
});
