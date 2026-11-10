<?php

namespace NetOs\Vitals\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use NetOs\Vitals\Dashboard\CheckAcrossTenants;
use NetOs\Vitals\Dashboard\Snapshots;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Models\Mute;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Vitals;

final readonly class CheckTenantsController
{
    public function __construct(
        private Vitals $vitals,
        private Snapshots $snapshots,
        private Mutes $mutes,
        private Factory $views,
    ) {}

    public function __invoke(string $check): View
    {
        $registered = $this->vitals->find(Scope::Tenant, $check);

        abort_if($registered === null, 404);

        $now = CarbonImmutable::now();
        $allTenantsMute = $this->mutes->active($now)->first(
            fn (Mute $mute): bool => $mute->scope === Scope::Tenant && $mute->check_key === $check && $mute->tenant_key === null,
        );

        return $this->views->make('vitals::check-tenants', [
            'matrix' => CheckAcrossTenants::from($registered, $this->snapshots->tenants($now), $allTenantsMute),
            'now' => $now,
        ]);
    }
}
