<?php

namespace NetOs\Vitals\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use NetOs\Vitals\Enums\Status;
use NetOs\Vitals\Models\Concerns\UsesVitalsConnection;

/**
 * A status change of a check, for the "Recent changes" feed. The first run of a check is not a change.
 *
 * @property int $id
 * @property string $check_key
 * @property string|null $tenant_key
 * @property Status $from_status
 * @property Status $to_status
 * @property string $summary
 * @property CarbonImmutable $changed_at
 */
class CheckChange extends Model
{
    use UsesVitalsConnection;

    protected $table = 'vitals_changes';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_status' => Status::class,
            'to_status' => Status::class,
            'changed_at' => 'immutable_datetime',
        ];
    }
}
