<?php

namespace NetOs\Vitals\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Models\Concerns\UsesVitalsConnection;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Support\Formatter;

/**
 * Keeps a problem out of the counters and status.json. A null check_key covers every tenant check of
 * the tenant; a tenant check with a null tenant_key covers that check for every tenant.
 *
 * @property int $id
 * @property Scope $scope
 * @property string|null $check_key
 * @property string|null $tenant_key
 * @property string|null $reason
 * @property CarbonImmutable|null $until
 * @property bool $until_recovered
 * @property CarbonImmutable $created_at
 */
class Mute extends Model
{
    use UsesVitalsConnection;

    public const UPDATED_AT = null;

    protected $table = 'vitals_mutes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope' => Scope::class,
            'until' => 'immutable_datetime',
            'until_recovered' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function covers(RegisteredCheck $check, ?string $tenantKey): bool
    {
        return $this->scope === $check->scope
            && ($this->check_key === null || $this->check_key === $check->key())
            && ($this->tenant_key === null || $this->tenant_key === $tenantKey);
    }

    /**
     * Whether this mute targets exactly this check, so it can be lifted from that check.
     */
    public function targets(RegisteredCheck $check, ?string $tenantKey): bool
    {
        return $this->scope === $check->scope && $this->check_key === $check->key() && $this->tenant_key === $tenantKey;
    }

    public function durationLabel(): string
    {
        return match (true) {
            $this->until_recovered => 'until it recovers',
            $this->until !== null => 'until '.Formatter::dateTime($this->until),
            default => 'until unmuted',
        };
    }

    public function targetLabel(): string
    {
        return match (true) {
            $this->scope === Scope::Central && $this->check_key === null => 'All central checks',
            $this->scope === Scope::Central => 'Central check '.$this->check_key,
            $this->check_key === null && $this->tenant_key === null => 'All tenant checks',
            $this->check_key === null => 'All checks of tenant '.$this->tenant_key,
            $this->tenant_key === null => 'Check '.$this->check_key.' for all tenants',
            default => 'Check '.$this->check_key.' for tenant '.$this->tenant_key,
        };
    }

    public function description(): string
    {
        return 'Muted '.$this->durationLabel().($this->reason !== null && $this->reason !== '' ? ': '.$this->reason : '');
    }
}
