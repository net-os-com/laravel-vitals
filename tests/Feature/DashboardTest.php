<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Jobs\RunCheckJob;
use NetOs\Vitals\Jobs\RunTenantChecksJob;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\ProcessCheck;
use NetOs\Vitals\Tenancy\NullTenancyAdapter;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

beforeEach(function () {
    Gate::define('viewVitals', fn ($user = null) => true);
    CarbonImmutable::setTestNow('2026-11-05 14:32:00');

    Vitals::central([
        (new FixedCheck(Result::ok('Connected in 3 ms')))->withKey('database'),
        (new FixedCheck(Result::warning('Memory at 412 MB of the 500 MB limit')))->withKey('redis'),
    ]);
    Vitals::tenant([
        (new FixedCheck(Result::failed('401 Unauthorized')->meta(['grant' => 'client_credentials'])))->withKey('auth0-sync')->withLabel('Auth0 sync'),
        (new FixedCheck(Result::ok()))->withKey('kiosk')->onlyFor(fn (TenantReference $tenant) => $tenant->key === 'globex'),
    ]);

    fakeTenants(['acme', 'globex']);
});

function recordRuns(): void
{
    $process = app(ProcessCheck::class);
    $tenant = new TenantReference('acme', 'Acme', 'acme.test');

    foreach (Vitals::checks(Scope::Central) as $check) {
        $process->handle($check);
    }

    $process->handle(Vitals::find(Scope::Tenant, 'auth0-sync'), $tenant);
    $process->handle(Vitals::find(Scope::Tenant, 'auth0-sync'), $tenant);
}

it('denies access without permission', function () {
    Gate::define('viewVitals', fn ($user = null) => false);

    $this->get('/vitals')->assertForbidden();
});

it('shows the overview with tenants that need attention first', function () {
    recordRuns();

    $this->get('/vitals')
        ->assertOk()
        ->assertSeeText('1 tenant needs attention')
        ->assertSeeText('2 tenants and 2 central checks.')
        ->assertSeeText('Needs attention')
        ->assertSeeText('Auth0 sync · 401 Unauthorized')
        ->assertSeeText('Memory at 412 MB of the 500 MB limit')
        ->assertSeeText('1 of 2 OK')
        ->assertSeeText('Checks are not being dispatched.');
});

it('shows a tenant with its checks, problems first and not applicable last', function () {
    recordRuns();

    $this->get('/vitals/tenants/acme')
        ->assertOk()
        ->assertSeeTextInOrder(['Acme', '1 failing', 'acme.test', '1 check'])
        ->assertSeeText('Auth0 sync has failed 2 times in a row since Today, 14:32.')
        ->assertSeeTextInOrder(['Auth0 sync', 'Not applicable']);
});

it('returns 404 for an unknown tenant or check', function () {
    $this->get('/vitals/tenants/nope')->assertNotFound();
    $this->get('/vitals/checks/nope')->assertNotFound();
    $this->get('/vitals/checks/auth0-sync?tenant=nope')->assertNotFound();
});

it('shows the detail page of a tenant check with history', function () {
    recordRuns();

    $this->get('/vitals/checks/auth0-sync?tenant=acme')
        ->assertOk()
        ->assertSeeTextInOrder(['Overview', 'Acme', 'Auth0 sync'])
        ->assertSeeText('Tenant check')
        ->assertSeeText('2 consecutive failures')
        ->assertSeeText('client_credentials')
        ->assertSeeText('2 runs')
        ->assertSeeText('0%');
});

it('shows the detail page of a central check without a tenant crumb', function () {
    recordRuns();

    $this->get('/vitals/checks/database?range=24h')
        ->assertOk()
        ->assertSeeText('Central check')
        ->assertSeeText('100%')
        ->assertDontSeeText('Acme');
});

it('queues a check from run now', function () {
    Queue::fake();

    $this->post('/vitals/checks/database/run')->assertStatus(202);
    $this->post('/vitals/checks/auth0-sync/run?tenant=acme')->assertStatus(202);
    $this->post('/vitals/checks/kiosk/run?tenant=acme')->assertNotFound();

    Queue::assertPushed(RunCheckJob::class, fn (RunCheckJob $job) => $job->checkKey === 'database');
    Queue::assertPushed(RunTenantChecksJob::class, fn (RunTenantChecksJob $job) => $job->tenantKey === 'acme' && $job->checkKeys === ['auth0-sync']);
});

it('queues all checks of a tenant or all checks', function () {
    Queue::fake();

    $this->post('/vitals/tenants/globex/run')->assertStatus(202)->assertJson(['dispatched' => 1]);
    $this->post('/vitals/run')->assertStatus(202)->assertJson(['dispatched' => 4]);

    Queue::assertPushed(RunTenantChecksJob::class, fn (RunTenantChecksJob $job) => $job->tenantKey === 'globex' && $job->checkKeys === ['auth0-sync', 'kiosk']);
});

it('shows the overview without tenancy', function () {
    app()->instance(TenancyAdapter::class, new NullTenancyAdapter);
    Vitals::flush();
    Vitals::central([(new FixedCheck(Result::ok()))->withKey('database')]);

    $this->get('/vitals')
        ->assertOk()
        ->assertSeeText('1 central check.')
        ->assertSeeText('Healthy checks')
        ->assertDontSeeText('All tenants');
});
