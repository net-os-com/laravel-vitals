<?php

namespace NetOs\Vitals\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use NetOs\Vitals\Dashboard\Snapshots;

final readonly class TenantController
{
    public function __construct(private Snapshots $snapshots, private Factory $views) {}

    public function __invoke(string $tenant): View
    {
        $reference = $this->snapshots->findTenant($tenant);

        abort_if($reference === null, 404);

        $now = CarbonImmutable::now();

        return $this->views->make('vitals::tenant', [
            'snapshot' => $this->snapshots->tenant($reference, $now),
            'now' => $now,
        ]);
    }
}
