<?php

namespace NetOs\Vitals\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Scheduling\Dispatcher;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Vitals;

final readonly class RunCheckController
{
    public function __construct(
        private Vitals $vitals,
        private TenancyAdapter $tenancy,
        private Dispatcher $dispatcher,
    ) {}

    public function __invoke(Request $request, string $check): JsonResponse
    {
        $tenantKey = $request->query('tenant');

        if (! is_string($tenantKey) || $tenantKey === '') {
            $registered = $this->vitals->find(Scope::Central, $check);
            abort_if($registered === null, 404);

            return response()->json(['dispatched' => $this->dispatcher->dispatchCentral([$registered])], 202);
        }

        $tenant = $this->tenancy->find($tenantKey);
        $registered = $this->vitals->find(Scope::Tenant, $check);

        abort_if($tenant === null || $registered === null || ! $registered->check->appliesTo($tenant), 404);

        return response()->json(['dispatched' => $this->dispatcher->dispatchTenant($tenant, [$registered])], 202);
    }
}
