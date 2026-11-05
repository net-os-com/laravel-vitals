<?php

namespace NetOs\Vitals\Http\Controllers;

use Illuminate\Http\JsonResponse;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Scheduling\DueChecks;
use NetOs\Vitals\Tenancy\TenancyAdapter;

final readonly class RunTenantController
{
    public function __construct(
        private TenancyAdapter $tenancy,
        private DueChecks $dueChecks,
        private Dispatcher $dispatcher,
    ) {}

    public function __invoke(string $tenant): JsonResponse
    {
        $reference = $this->tenancy->find($tenant);

        abort_if($reference === null, 404);

        return response()->json([
            'dispatched' => $this->dispatcher->dispatchTenant($reference, $this->dueChecks->applicable($reference)),
        ], 202);
    }
}
