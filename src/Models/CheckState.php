<?php

namespace NetOs\Vitals\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Models\Concerns\UsesVitalsConnection;

/**
 * @property int $id
 * @property string $check_key
 * @property string|null $tenant_key
 * @property Status $status
 * @property string $summary
 * @property CarbonImmutable $last_run_at
 * @property CarbonImmutable $status_since
 * @property CarbonImmutable|null $last_success_at
 * @property int $consecutive_failures
 */
class CheckState extends Model
{
    use UsesVitalsConnection;

    protected $table = 'vitals_states';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'consecutive_failures' => 'integer',
            'last_run_at' => 'immutable_datetime',
            'status_since' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
        ];
    }
}
