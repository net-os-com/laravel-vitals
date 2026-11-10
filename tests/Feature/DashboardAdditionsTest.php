<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\CheckRun;
use NetOs\Vitals\Running\ProcessCheck;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

beforeEach(function () {
    Gate::define('viewVitals', fn ($user = null) => true);
    CarbonImmutable::setTestNow('2026-11-10 09:00:00');
    Cache::forever(Dispatcher::HEARTBEAT_KEY, now()->getTimestamp());

    fakeTenants(['acme', 'globex']);

    Vitals::central([(new FixedCheck(Result::ok()))->withKey('cache')->runbook('https://example.com/runbooks/cache')]);
    Vitals::tenant([(new FixedCheck(Result::ok()))->withKey('auth0')->withLabel('Auth0 sync')]);
});

/**
 * @param  array<int, Status>  $statuses
 */
function recordStatuses(string $scope, string $key, ?string $tenant, array $statuses): void
{
    $check = Vitals::find(Scope::from($scope), $key);
    $reference = $tenant === null ? null : new TenantReference($tenant, ucfirst($tenant));

    foreach ($statuses as $index => $status) {
        $at = CarbonImmutable::now()->subMinutes((count($statuses) - $index) * 5);
        $result = match ($status) {
            Status::Ok => Result::ok('Fine'),
            Status::Warning => Result::warning('Slow'),
            default => Result::failed('Down'),
        };

        app(ProcessCheck::class)->record($check, $reference, new CheckRun($result, $at, $at, 3));
    }
}

it('records status changes, but not the first run', function () {
    recordStatuses('tenant', 'auth0', 'acme', [Status::Ok, Status::Ok, Status::Failed, Status::Ok]);

    $this->assertDatabaseCount('vitals_changes', 2);
    $this->assertDatabaseHas('vitals_changes', ['check_key' => 'auth0', 'tenant_key' => 'acme', 'from_status' => 'ok', 'to_status' => 'failed', 'summary' => 'Down']);
    $this->assertDatabaseHas('vitals_changes', ['from_status' => 'failed', 'to_status' => 'ok']);
});

it('shows the recent changes on the overview', function () {
    recordStatuses('tenant', 'auth0', 'acme', [Status::Ok, Status::Failed]);

    $this->get('/vitals')
        ->assertOk()
        ->assertSeeText('Recent changes')
        ->assertSeeTextInOrder(['Auth0 sync · Acme', 'OK → Failed · Down']);
});

it('prunes old changes with the results', function () {
    recordStatuses('central', 'cache', null, [Status::Ok, Status::Failed]);
    CarbonImmutable::setTestNow('2026-11-30 09:00:00');

    $this->artisan('vitals:prune')->assertSuccessful();

    $this->assertDatabaseCount('vitals_changes', 0);
});

it('shows one tenant check for every tenant', function () {
    recordStatuses('tenant', 'auth0', 'acme', [Status::Failed]);
    recordStatuses('tenant', 'auth0', 'globex', [Status::Ok]);

    $this->get('/vitals/checks/auth0/tenants')
        ->assertOk()
        ->assertSeeTextInOrder(['Auth0 sync', 'for all tenants', '1 failing', 'Mute for all tenants', 'Acme', 'Failed', 'Globex', 'OK']);

    $this->get('/vitals/checks/cache/tenants')->assertNotFound();
    $this->get('/vitals')->assertSeeTextInOrder(['Tenant checks', 'Auth0 sync', '1 failing']);
});

it('marks a check that keeps switching between healthy and unhealthy as flapping', function () {
    recordStatuses('central', 'cache', null, [Status::Ok, Status::Failed, Status::Ok, Status::Failed, Status::Ok]);

    $this->get('/vitals/checks/cache')->assertOk()->assertSeeText('Flapping');
    $this->get('/vitals')->assertSeeText('Flapping');
});

it('does not mark a check with a single outage as flapping', function () {
    recordStatuses('central', 'cache', null, [Status::Ok, Status::Failed, Status::Failed, Status::Ok]);

    $this->get('/vitals/checks/cache')->assertOk()->assertDontSeeText('Flapping');
});

it('links each problem in status.json to its check page and runbook', function () {
    recordStatuses('central', 'cache', null, [Status::Failed]);

    $this->getJson('/vitals/status.json')
        ->assertJsonPath('problems.0.url', route('vitals.check', 'cache'))
        ->assertJsonPath('problems.0.runbook', 'https://example.com/runbooks/cache');

    $this->get('/vitals/checks/cache')->assertSee('https://example.com/runbooks/cache', false);
});

it('reports the queue backlog in status.json and fails when checks fall behind', function () {
    config(['queue.default' => 'database', 'queue.connections.database.connection' => 'testing']);
    Schema::create('jobs', function ($table) {
        $table->id();
        $table->string('queue');
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    recordStatuses('central', 'cache', null, [Status::Ok]);

    $this->getJson('/vitals/status.json')->assertOk()->assertJsonPath('queue.size', 0)->assertJsonPath('queue.behind', false);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => now()->subMinutes(20)->getTimestamp(),
        'created_at' => now()->subMinutes(20)->getTimestamp(),
    ]);

    $this->getJson('/vitals/status.json')
        ->assertStatus(503)
        ->assertJsonPath('queue.size', 1)
        ->assertJsonPath('queue.behind', true);

    $this->get('/vitals')->assertSeeText('Checks are falling behind.');
});
