<?php

namespace NetOs\Vitals\Checks;

use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class DiskSpaceCheck extends Check
{
    protected ?string $path = null;

    protected int $warnAbovePercentage = 80;

    protected int $failAbovePercentage = 90;

    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function warnWhenUsedAbove(int $percentage): static
    {
        $this->warnAbovePercentage = $percentage;

        return $this;
    }

    public function failWhenUsedAbove(int $percentage): static
    {
        $this->failAbovePercentage = $percentage;

        return $this;
    }

    public function run(): Result
    {
        $path = $this->path ?? base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return Result::failed("Could not read the disk space of {$path}");
        }

        $used = (int) round(($total - $free) / $total * 100);
        $summary = "{$used}% used, ".round($free / 1024 ** 3, 1).' GB free';
        $meta = ['path' => $path, 'used_percentage' => $used, 'free_gb' => round($free / 1024 ** 3, 1)];

        return match (true) {
            $used > $this->failAbovePercentage => Result::failed($summary)->meta($meta),
            $used > $this->warnAbovePercentage => Result::warning($summary)->meta($meta),
            default => Result::ok($summary)->meta($meta),
        };
    }
}
