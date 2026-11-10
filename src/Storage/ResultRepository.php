<?php

namespace NetOs\Vitals\Storage;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Models\CheckChange;
use NetOs\Vitals\Models\CheckResult;
use NetOs\Vitals\Models\CheckState;
use NetOs\Vitals\Running\CheckRun;

interface ResultRepository
{
    /**
     * Store the run and update the latest state. Returns the status before this run (null on the first run).
     */
    public function record(string $checkKey, ?string $tenantKey, CheckRun $run): ?Status;

    /**
     * @return Collection<int, CheckState>
     */
    public function states(?string $tenantKey = null, bool $allTenants = false): Collection;

    public function state(string $checkKey, ?string $tenantKey): ?CheckState;

    /**
     * The last results per check, newest first, keyed by "check_key|tenant_key".
     *
     * @return Collection<string, Collection<int, Status>>
     */
    public function recentStatuses(?string $tenantKey, int $limit, bool $allTenants = false): Collection;

    /**
     * @return Collection<int, CheckResult>
     */
    public function history(string $checkKey, ?string $tenantKey, CarbonInterface $since): Collection;

    /**
     * @return LengthAwarePaginator<int, CheckResult>
     */
    public function paginate(string $checkKey, ?string $tenantKey, CarbonInterface $since, int $perPage): LengthAwarePaginator;

    public function latest(string $checkKey, ?string $tenantKey): ?CheckResult;

    /**
     * The latest status changes over all checks, newest first.
     *
     * @return Collection<int, CheckChange>
     */
    public function changes(int $limit): Collection;

    public function prune(CarbonInterface $before): int;
}
