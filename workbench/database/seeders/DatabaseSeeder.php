<?php

namespace Workbench\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Facades\Vitals;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Result;
use NetOs\Vitals\Running\CheckRun;
use NetOs\Vitals\Storage\ResultRepository;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use Workbench\App\Models\Tenant;

/**
 * Three tenants: Acme (a failing sync), Globex (a pending migration) and Northwind (no database),
 * plus seven days of hourly history so the dashboard has something to show.
 */
class DatabaseSeeder extends Seeder
{
    public function run(ResultRepository $results, TenancyAdapter $tenancy): void
    {
        $this->tenant('acme', 'Acme Offices', kiosk: true, desks: 24, syncSucceeded: false);
        $this->tenant('globex', 'Globex Campus', kiosk: false, desks: 8, syncSucceeded: true, skipLastMigration: true);

        $northwind = Tenant::query()->create(['id' => 'northwind', 'name' => 'Northwind HQ', 'kiosk' => false]);
        $northwind->domains()->create(['domain' => 'northwind.localhost']);

        DB::transaction(function () use ($results, $tenancy) {
            foreach (Vitals::checks(Scope::Central) as $check) {
                $this->history($results, $check, null);
            }

            foreach ($tenancy->tenants() as $tenant) {
                foreach (Vitals::checks(Scope::Tenant) as $check) {
                    if ($check->check->appliesTo($tenant)) {
                        $this->history($results, $check, $tenant->key);
                    }
                }
            }
        });

        Artisan::call('vitals:run');
        Artisan::call('vitals:dispatch');
    }

    private function tenant(string $key, string $name, bool $kiosk, int $desks, bool $syncSucceeded, bool $skipLastMigration = false): void
    {
        $tenant = Tenant::query()->create(['id' => $key, 'name' => $name, 'kiosk' => $kiosk]);
        $tenant->domains()->create(['domain' => "{$key}.localhost"]);
        $tenant->database()->manager()->createDatabase($tenant);

        tenancy()->initialize($tenant);

        try {
            $paths = glob(__DIR__.'/../migrations/tenant/*.php') ?: [];
            sort($paths);

            Artisan::call('migrate', [
                '--path' => $skipLastMigration ? array_slice($paths, 0, -1) : $paths,
                '--realpath' => true,
                '--force' => true,
            ]);

            foreach (range(1, $desks) as $number) {
                DB::table('desks')->insert(['name' => "Desk {$number}", 'created_at' => now(), 'updated_at' => now()]);
            }

            DB::table('syncs')->insert([
                'succeeded' => $syncSucceeded,
                'message' => $syncSucceeded ? 'Synced 214 users' : '401 Unauthorized: client credentials rejected',
                'synced_at' => now()->subMinutes(14),
            ]);
        } finally {
            tenancy()->end();
        }
    }

    private function history(ResultRepository $results, RegisteredCheck $check, ?string $tenantKey): void
    {
        $now = CarbonImmutable::now()->startOfHour();

        for ($hoursAgo = 7 * 24; $hoursAgo >= 1; $hoursAgo--) {
            $finishedAt = $now->subHours($hoursAgo)->addMinutes(15);
            $result = $this->resultFor($check->key(), $tenantKey, $hoursAgo);
            $duration = random_int(3, 40) * ($check->key() === 'last-sync' ? 50 : 1);

            $results->record($check->key(), $tenantKey, new CheckRun(
                $result,
                $finishedAt->subMilliseconds($duration),
                $finishedAt,
                $duration,
            ));
        }
    }

    private function resultFor(string $check, ?string $tenant, int $hoursAgo): Result
    {
        return match (true) {
            $tenant === 'northwind' && $hoursAgo <= 3 => Result::crashed('Tenant could not be initialized: Database tenantnorthwind.sqlite does not exist.'),
            $tenant === 'acme' && $check === 'last-sync' && $hoursAgo <= 6 => Result::failed('401 Unauthorized: client credentials rejected'),
            $tenant === 'acme' && $check === 'last-sync' && $hoursAgo === 5 * 24 + 1 => Result::crashed('cURL error 28: Operation timed out'),
            $tenant === 'globex' && $check === 'pending-migrations' && $hoursAgo <= 48 => Result::warning('1 pending migration'),
            $tenant === null && $check === 'disk-space' && $hoursAgo >= 3 * 24 && $hoursAgo < 3 * 24 + 4 => Result::warning('91% used, 4.2 GB free'),
            default => $this->okResult($check),
        };
    }

    private function okResult(string $check): Result
    {
        return match ($check) {
            'last-sync' => Result::ok('Synced 214 users'),
            'pending-migrations' => Result::ok('No pending migrations'),
            'horizon' => Result::skipped('Horizon is not installed'),
            default => Result::ok('OK'),
        };
    }
}
