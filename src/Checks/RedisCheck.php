<?php

namespace NetOs\Vitals\Checks;

use Illuminate\Support\Facades\Redis;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class RedisCheck extends Check
{
    protected string $connection = 'default';

    protected ?int $warnAboveMb = null;

    protected ?int $failAboveMb = null;

    public function connection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function warnWhenMemoryAboveMb(int $megabytes): static
    {
        $this->warnAboveMb = $megabytes;

        return $this;
    }

    public function failWhenMemoryAboveMb(int $megabytes): static
    {
        $this->failAboveMb = $megabytes;

        return $this;
    }

    public function run(): Result
    {
        $connection = Redis::connection($this->connection);
        $connection->command('ping');

        $usedMb = $this->usedMemoryMb($connection->command('info', ['memory']));
        $meta = ['connection' => $this->connection, 'used_memory_mb' => $usedMb];

        if ($usedMb === null) {
            return Result::ok('Ping succeeded')->meta($meta);
        }

        $limit = $this->failAboveMb ?? $this->warnAboveMb;
        $summary = $limit === null
            ? "Ping succeeded, {$usedMb} MB in use"
            : "Memory at {$usedMb} MB of the {$limit} MB limit";

        return match (true) {
            $this->failAboveMb !== null && $usedMb > $this->failAboveMb => Result::failed($summary)->meta($meta),
            $this->warnAboveMb !== null && $usedMb > $this->warnAboveMb => Result::warning($summary)->meta($meta),
            $this->failAboveMb !== null && $usedMb > $this->failAboveMb * 0.8 => Result::warning($summary)->meta($meta),
            default => Result::ok($summary)->meta($meta),
        };
    }

    private function usedMemoryMb(mixed $info): ?int
    {
        if (! is_array($info)) {
            return null;
        }

        // phpredis returns a flat array, predis groups the values per section.
        $bytes = $info['used_memory'] ?? $info['Memory']['used_memory'] ?? null;

        return is_numeric($bytes) ? (int) round($bytes / 1024 / 1024) : null;
    }
}
