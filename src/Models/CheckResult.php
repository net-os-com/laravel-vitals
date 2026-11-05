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
 * @property array<string, scalar|null>|null $meta
 * @property int $duration_ms
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $finished_at
 */
class CheckResult extends Model
{
    use UsesVitalsConnection;

    protected $table = 'vitals_results';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'meta' => 'array',
            'duration_ms' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
