<?php

namespace NetOs\Vitals\Checks;

use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

/**
 * Counts the failed jobs of the last hour in the database failed-job table.
 */
class FailedJobsCheck extends Check
{
    protected int $warnAbove = 0;

    protected int $failAbove = 25;

    protected int $withinMinutes = 60;

    public function warnWhenMoreThan(int $count): static
    {
        $this->warnAbove = $count;

        return $this;
    }

    public function failWhenMoreThan(int $count): static
    {
        $this->failAbove = $count;

        return $this;
    }

    public function withinMinutes(int $minutes): static
    {
        $this->withinMinutes = $minutes;

        return $this;
    }

    public function run(): Result
    {
        $connection = config('queue.failed.database');
        $table = config('queue.failed.table', 'failed_jobs');

        $count = DB::connection(is_string($connection) ? $connection : null)
            ->table(is_string($table) ? $table : 'failed_jobs')
            ->where('failed_at', '>=', now()->subMinutes($this->withinMinutes))
            ->count();

        $period = $this->withinMinutes === 60 ? 'the last hour' : "the last {$this->withinMinutes} minutes";
        $summary = $count === 0 ? "No failed jobs in {$period}" : "{$count} failed ".str('job')->plural($count)." in {$period}";
        $meta = ['failed_jobs' => $count, 'within_minutes' => $this->withinMinutes];

        return match (true) {
            $count > $this->failAbove => Result::failed($summary)->meta($meta),
            $count > $this->warnAbove => Result::warning($summary)->meta($meta),
            default => Result::ok($summary)->meta($meta),
        };
    }
}
