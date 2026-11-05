<?php

use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Checks\CacheCheck;
use NetOs\Vitals\Checks\DatabaseCheck;
use NetOs\Vitals\Checks\DebugModeCheck;
use NetOs\Vitals\Checks\DiskSpaceCheck;
use NetOs\Vitals\Checks\EnvironmentCheck;
use NetOs\Vitals\Checks\FailedJobsCheck;
use NetOs\Vitals\Checks\HorizonCheck;
use NetOs\Vitals\Checks\OptimizedAppCheck;
use NetOs\Vitals\Checks\PendingMigrationsCheck;
use NetOs\Vitals\Checks\SchedulerHeartbeatCheck;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Scheduling\Dispatcher;

it('checks the database connection', function () {
    $result = DatabaseCheck::new()->run();

    expect($result->status)->toBe(Status::Ok)
        ->and($result->meta['driver'])->toBe('sqlite');
});

it('checks the cache', function () {
    expect(CacheCheck::new()->run()->status)->toBe(Status::Ok);
});

it('checks the disk space against thresholds', function () {
    expect(DiskSpaceCheck::new()->run()->status)->not->toBe(Status::Crashed)
        ->and(DiskSpaceCheck::new()->failWhenUsedAbove(-1)->run()->status)->toBe(Status::Failed);
});

it('checks debug mode and the environment', function () {
    config(['app.debug' => true]);

    expect(DebugModeCheck::new()->run()->status)->toBe(Status::Failed)
        ->and(DebugModeCheck::new()->expectedToBe(true)->run()->status)->toBe(Status::Ok)
        ->and(EnvironmentCheck::new()->run()->summary)->toBe('Running in testing, expected production')
        ->and(EnvironmentCheck::new()->expect('testing')->run()->status)->toBe(Status::Ok);
});

it('warns about uncached config outside local', function () {
    expect(OptimizedAppCheck::new()->run()->status)->toBe(Status::Warning);
});

it('skips Horizon when it is not installed', function () {
    expect(HorizonCheck::new()->run()->status)->toBe(Status::Skipped);
});

it('fails the scheduler heartbeat when the dispatcher did not run recently', function () {
    expect(SchedulerHeartbeatCheck::new()->run()->status)->toBe(Status::Failed);

    app(Dispatcher::class)->dispatchDue(now());

    expect(SchedulerHeartbeatCheck::new()->run()->status)->toBe(Status::Ok);
});

it('counts failed jobs of the last hour', function () {
    config(['queue.failed.database' => 'testing']);
    DB::connection()->getSchemaBuilder()->create('failed_jobs', function ($table) {
        $table->id();
        $table->text('payload');
        $table->timestamp('failed_at');
    });
    DB::table('failed_jobs')->insert([
        ['payload' => '{}', 'failed_at' => now()->subMinutes(5)],
        ['payload' => '{}', 'failed_at' => now()->subHours(3)],
    ]);

    $result = FailedJobsCheck::new()->run();

    expect($result->status)->toBe(Status::Warning)
        ->and($result->summary)->toBe('1 failed job in the last hour')
        ->and(FailedJobsCheck::new()->warnWhenMoreThan(5)->run()->status)->toBe(Status::Ok);
});

it('reports pending migrations', function () {
    $directory = sys_get_temp_dir().'/vitals-migrations-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/2026_01_01_000000_create_things.php', '<?php return new class extends Illuminate\Database\Migrations\Migration { public function up(): void {} };');

    $this->artisan('migrate:install');

    $result = PendingMigrationsCheck::new()->paths([$directory])->run();

    expect($result->status)->toBe(Status::Warning)
        ->and($result->summary)->toBe('1 pending migration')
        ->and(PendingMigrationsCheck::new()->paths([$directory])->failWhenPending()->run()->status)->toBe(Status::Failed);

    $this->artisan('migrate', ['--path' => $directory, '--realpath' => true]);

    expect(PendingMigrationsCheck::new()->paths([$directory])->run()->status)->toBe(Status::Ok);
});
