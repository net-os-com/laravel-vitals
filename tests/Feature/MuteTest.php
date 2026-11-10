<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Events\CheckFinished;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Models\Mute;
use NetOs\Vitals\Muting\MuteDuration;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\ProcessCheck;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

beforeEach(function () {
    Gate::define('viewVitals', fn ($user = null) => true);
    CarbonImmutable::setTestNow('2026-11-10 09:00:00');
    Cache::forever(Dispatcher::HEARTBEAT_KEY, now()->getTimestamp());

    fakeTenants(['acme', 'globex']);

    Vitals::central([(new FixedCheck(Result::failed('Disk almost full')))->withKey('disk')]);
    Vitals::tenant([
        (new FixedCheck(Result::failed('401 Unauthorized')))->withKey('auth0'),
        (new FixedCheck(Result::warning('1 pending migration')))->withKey('migrations'),
    ]);
});

function runAllChecks(): void
{
    $process = app(ProcessCheck::class);
    $process->handle(Vitals::find(Scope::Central, 'disk'));

    foreach (['acme', 'globex'] as $key) {
        $tenant = new TenantReference($key, ucfirst($key));

        foreach (Vitals::checks(Scope::Tenant) as $check) {
            $process->handle($check, $tenant);
        }
    }
}

/**
 * @return array<int, string>
 */
function statusProblems(): array
{
    return collect(test()->getJson('/vitals/status.json')->json('problems'))
        ->map(fn (array $problem): string => ($problem['tenant'] ?? 'central').':'.$problem['check'])
        ->all();
}

it('stores a mute from the dashboard', function () {
    $this->postJson('/vitals/mutes', [
        'scope' => 'tenant',
        'check' => 'auth0',
        'tenant' => 'acme',
        'duration' => '4h',
        'reason' => 'Rotating the client secret',
    ])->assertCreated()->assertJsonPath('description', 'Muted until Today, 13:00: Rotating the client secret');

    $this->assertDatabaseHas('vitals_mutes', [
        'scope' => 'tenant',
        'check_key' => 'auth0',
        'tenant_key' => 'acme',
        'reason' => 'Rotating the client secret',
        'until' => '2026-11-10 13:00:00',
        'until_recovered' => false,
    ]);
});

it('rejects a mute without a target or with an unknown check or tenant', function (array $payload, string $field) {
    $this->postJson('/vitals/mutes', [...$payload, 'duration' => '1h'])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no target' => [['scope' => 'tenant'], 'check'],
    'central without check' => [['scope' => 'central', 'tenant' => 'acme'], 'check'],
    'unknown check' => [['scope' => 'central', 'check' => 'nope'], 'check'],
    'unknown tenant' => [['scope' => 'tenant', 'tenant' => 'nope'], 'tenant'],
]);

it('leaves muted problems out of status.json and lists them separately', function () {
    runAllChecks();
    app(Mutes::class)->mute(Scope::Tenant, 'auth0', 'acme', MuteDuration::OneDay, 'Known issue');

    expect(statusProblems())->toBe(['central:disk', 'acme:migrations', 'globex:auth0', 'globex:migrations']);

    $this->getJson('/vitals/status.json')
        ->assertJsonPath('muted.0.check', 'auth0')
        ->assertJsonPath('muted.0.tenant', 'acme')
        ->assertJsonPath('muted.0.reason', 'Known issue');
});

it('mutes every check of a tenant or one check for every tenant', function () {
    runAllChecks();
    app(Mutes::class)->mute(Scope::Tenant, null, 'acme', MuteDuration::UntilUnmuted, null);
    app(Mutes::class)->mute(Scope::Tenant, 'migrations', null, MuteDuration::UntilUnmuted, null);

    expect(statusProblems())->toBe(['central:disk', 'globex:auth0']);
});

it('returns 200 once every problem is muted', function () {
    runAllChecks();
    app(Mutes::class)->mute(Scope::Central, 'disk', null, MuteDuration::OneHour, null);
    app(Mutes::class)->mute(Scope::Tenant, 'auth0', null, MuteDuration::OneHour, null);
    app(Mutes::class)->mute(Scope::Tenant, 'migrations', null, MuteDuration::OneHour, null);

    $this->getJson('/vitals/status.json')->assertOk()->assertJsonPath('status', 'ok');
});

it('ignores a mute once its period has passed and prunes it', function () {
    runAllChecks();
    app(Mutes::class)->mute(Scope::Central, 'disk', null, MuteDuration::OneHour, null);

    CarbonImmutable::setTestNow('2026-11-10 10:00:01');
    Cache::forever(Dispatcher::HEARTBEAT_KEY, now()->getTimestamp());

    expect(statusProblems())->toContain('central:disk');

    $this->artisan('vitals:prune')->assertSuccessful();
    $this->assertDatabaseCount('vitals_mutes', 0);
});

it('lifts an until-recovered mute once none of its checks has a problem', function () {
    $outcome = Result::failed('Down');
    Vitals::flush();
    Vitals::central([(new FixedCheck(function () use (&$outcome): Result {
        return $outcome;
    }))->withKey('flaky')]);
    $check = Vitals::find(Scope::Central, 'flaky');

    app(ProcessCheck::class)->handle($check);
    app(Mutes::class)->mute(Scope::Central, 'flaky', null, MuteDuration::UntilRecovered, null);

    app(ProcessCheck::class)->handle($check);
    $this->assertDatabaseCount('vitals_mutes', 1);

    $outcome = Result::ok();
    app(ProcessCheck::class)->handle($check);
    $this->assertDatabaseCount('vitals_mutes', 0);
});

it('keeps an until-recovered mute for all tenants while one tenant still has the problem', function () {
    runAllChecks();
    app(Mutes::class)->mute(Scope::Tenant, 'auth0', null, MuteDuration::UntilRecovered, null);

    Vitals::flush();
    Vitals::tenant([(new FixedCheck(Result::ok()))->withKey('auth0')]);
    app(ProcessCheck::class)->handle(Vitals::find(Scope::Tenant, 'auth0'), new TenantReference('acme', 'Acme'));

    $this->assertDatabaseCount('vitals_mutes', 1);

    app(ProcessCheck::class)->handle(Vitals::find(Scope::Tenant, 'auth0'), new TenantReference('globex', 'Globex'));

    $this->assertDatabaseCount('vitals_mutes', 0);
});

it('flags the events of a muted check', function () {
    Event::fake([CheckFinished::class]);
    app(Mutes::class)->mute(Scope::Central, 'disk', null, MuteDuration::OneDay, null);

    app(ProcessCheck::class)->handle(Vitals::find(Scope::Central, 'disk'));

    Event::assertDispatched(CheckFinished::class, fn (CheckFinished $event) => $event->muted);
});

it('unmutes from the dashboard', function () {
    $mute = app(Mutes::class)->mute(Scope::Central, 'disk', null, MuteDuration::OneDay, null);

    $this->deleteJson("/vitals/mutes/{$mute->id}")->assertNoContent();
    $this->deleteJson("/vitals/mutes/{$mute->id}")->assertNotFound();

    $this->assertDatabaseMissing('vitals_mutes', ['id' => $mute->id]);
});

it('shows muted problems in the dashboard without counting them', function () {
    runAllChecks();
    app(Mutes::class)->mute(Scope::Tenant, null, 'acme', MuteDuration::UntilUnmuted, 'Migrating the database');

    $this->get('/vitals')
        ->assertOk()
        ->assertSeeText('1 tenant needs attention')
        ->assertSeeText('All checks of tenant acme')
        ->assertSeeText('Muted until unmuted: Migrating the database')
        ->assertSeeText('All OK, 2 muted');

    $this->get('/vitals/tenants/acme')
        ->assertOk()
        ->assertSeeText('Unmute tenant')
        ->assertSeeText('2 muted');
});

it('describes what a mute covers', function (array $attributes, string $label) {
    expect((new Mute($attributes))->targetLabel())->toBe($label);
})->with([
    [['scope' => Scope::Central, 'check_key' => 'disk'], 'Central check disk'],
    [['scope' => Scope::Tenant, 'tenant_key' => 'acme'], 'All checks of tenant acme'],
    [['scope' => Scope::Tenant, 'check_key' => 'auth0'], 'Check auth0 for all tenants'],
    [['scope' => Scope::Tenant, 'check_key' => 'auth0', 'tenant_key' => 'acme'], 'Check auth0 for tenant acme'],
]);
