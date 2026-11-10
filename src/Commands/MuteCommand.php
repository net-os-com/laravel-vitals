<?php

namespace NetOs\Vitals\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Models\Mute;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Vitals;

/**
 * Mute checks from the command line, for example around a deploy:
 * vitals:mute --all --for=15m --reason=Deploy, and afterwards vitals:unmute --reason=Deploy.
 */
final class MuteCommand extends Command
{
    protected $signature = 'vitals:mute
        {check? : The key of the check to mute}
        {--tenant= : Only for this tenant; without a check, every check of this tenant}
        {--central : Mute the central check with this key when a tenant check has the same key}
        {--all : Mute every central and tenant check}
        {--for= : How long, such as 15m, 2h or 1d (default: until unmuted)}
        {--until-recovered : Lift the mute once the muted checks are healthy again}
        {--reason= : Why the check is muted}';

    protected $description = 'Mute checks so they do not count in the totals or in status.json';

    public function handle(Mutes $mutes, Vitals $vitals, TenancyAdapter $tenancy): int
    {
        $until = $this->until();

        if ($until === false) {
            $this->components->error('Use --for with minutes, hours or days, such as 15m, 2h or 1d.');

            return self::FAILURE;
        }

        $targets = $this->targets($vitals, $tenancy);

        if (is_string($targets)) {
            $this->components->error($targets);

            return self::FAILURE;
        }

        $reason = $this->option('reason');

        foreach ($targets as [$scope, $check, $tenant]) {
            $mute = $mutes->muteUntil($scope, $check, $tenant, $until, (bool) $this->option('until-recovered'), is_string($reason) ? $reason : null);

            $this->components->info("Muted {$this->describe($mute)} (#{$mute->id}).");
        }

        return self::SUCCESS;
    }

    private function until(): CarbonImmutable|false|null
    {
        $for = $this->option('for');

        if (! is_string($for) || $for === '') {
            return null;
        }

        if (! preg_match('/^(\d+)\s*(m|h|d)$/', $for, $match)) {
            return false;
        }

        $now = CarbonImmutable::now();

        return match ($match[2]) {
            'm' => $now->addMinutes((int) $match[1]),
            'h' => $now->addHours((int) $match[1]),
            default => $now->addDays((int) $match[1]),
        };
    }

    /**
     * @return array<int, array{0: Scope, 1: string|null, 2: string|null}>|string
     */
    private function targets(Vitals $vitals, TenancyAdapter $tenancy): array|string
    {
        $check = $this->argument('check');
        $check = is_string($check) && $check !== '' ? $check : null;
        $tenant = $this->option('tenant');
        $tenant = is_string($tenant) && $tenant !== '' ? $tenant : null;

        if ($tenant !== null && $tenancy->find($tenant) === null) {
            return "Tenant [{$tenant}] not found.";
        }

        if ($this->option('all')) {
            return [[Scope::Central, null, null], [Scope::Tenant, null, null]];
        }

        if ($check === null) {
            return $tenant === null ? 'Pass a check, --tenant or --all.' : [[Scope::Tenant, null, $tenant]];
        }

        $central = $vitals->find(Scope::Central, $check) !== null;
        $tenantCheck = $vitals->find(Scope::Tenant, $check) !== null;

        return match (true) {
            $central && ($this->option('central') || ! $tenantCheck) && $tenant === null => [[Scope::Central, $check, null]],
            $tenantCheck => [[Scope::Tenant, $check, $tenant]],
            default => "There is no check [{$check}].",
        };
    }

    private function describe(Mute $mute): string
    {
        return lcfirst($mute->targetLabel()).' '.$mute->durationLabel();
    }
}
