<?php

namespace NetOs\Vitals\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Running\CheckRun;
use NetOs\Vitals\Running\ProcessCheck;
use NetOs\Vitals\Running\ProcessTenantChecks;
use NetOs\Vitals\Scheduling\DueChecks;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Tenancy\TenantReference;
use NetOs\Vitals\Vitals;

final class RunCommand extends Command
{
    protected $signature = 'vitals:run
        {check? : Only run the check with this key}
        {--tenant= : Only run the tenant checks of this tenant}';

    protected $description = 'Run checks synchronously and show the results';

    /** @var array<int, array<int, string>> */
    private array $rows = [];

    private bool $failing = false;

    public function handle(
        Vitals $vitals,
        TenancyAdapter $tenancy,
        DueChecks $dueChecks,
        ProcessCheck $processCheck,
        ProcessTenantChecks $processTenantChecks,
    ): int {
        $key = $this->argument('check');
        $key = is_string($key) ? $key : null;
        $tenantKey = $this->option('tenant');
        $tenantKey = is_string($tenantKey) ? $tenantKey : null;

        if ($tenantKey === null) {
            $this->runCentral($vitals->checks(Scope::Central), $key, $processCheck);
        }

        $tenants = $tenantKey === null ? $tenancy->tenants() : new Collection(array_filter([$tenancy->find($tenantKey)]));

        if ($tenantKey !== null && $tenants->isEmpty()) {
            $this->components->error("Tenant [{$tenantKey}] not found.");

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $checks = $dueChecks->applicable($tenant)
                ->filter(fn (RegisteredCheck $check): bool => $key === null || $check->key() === $key);

            foreach ($processTenantChecks->handle($tenant, $checks) as $checkKey => $run) {
                $this->addRow($checks->first(fn (RegisteredCheck $check): bool => $check->key() === $checkKey), $tenant, $run);
            }
        }

        if ($this->rows === []) {
            $this->components->warn('No checks matched.');

            return self::FAILURE;
        }

        $this->table(['Check', 'Tenant', 'Status', 'Summary', 'Duration'], $this->rows);

        return $this->failing ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  Collection<int, RegisteredCheck>  $checks
     */
    private function runCentral(Collection $checks, ?string $key, ProcessCheck $processCheck): void
    {
        foreach ($checks as $check) {
            if ($key === null || $check->key() === $key) {
                $this->addRow($check, null, $processCheck->handle($check));
            }
        }
    }

    private function addRow(?RegisteredCheck $check, ?TenantReference $tenant, CheckRun $run): void
    {
        $status = $run->result->status;
        $this->failing = $this->failing || $status->isFailing();

        $color = match (true) {
            $status->isFailing() => 'red',
            $status->isHealthy() => 'green',
            default => 'yellow',
        };

        $this->rows[] = [
            $check?->label() ?? '?',
            $tenant === null ? '—' : $tenant->name,
            "<fg={$color}>{$status->label()}</>",
            $run->result->summary,
            "{$run->durationMs} ms",
        ];
    }
}
