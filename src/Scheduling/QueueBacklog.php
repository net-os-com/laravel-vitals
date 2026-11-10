<?php

namespace NetOs\Vitals\Scheduling;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Throwable;

/**
 * How many check jobs wait on the Vitals queue and how old the oldest one is. Measured live by the
 * dashboard and status.json, so it still works when the queue itself is stuck.
 */
final readonly class QueueBacklog
{
    public function __construct(private QueueFactory $queues) {}

    /**
     * @return array{queue: string, size: int|null, oldest_seconds: int|null}
     */
    public function measure(): array
    {
        $connection = config('vitals.queue_connection');
        $queueName = config('vitals.queue');
        $queueName = is_string($queueName) && $queueName !== '' ? $queueName : null;

        try {
            $queue = $this->queues->connection(is_string($connection) && $connection !== '' ? $connection : null);
            // pendingSize() and creationTimeOfOldestPendingJob() are not on the Queue contract of every supported Laravel version.
            $pendingSize = [$queue, 'pendingSize'];
            $oldestCreatedAt = [$queue, 'creationTimeOfOldestPendingJob'];
            $size = is_callable($pendingSize) ? $pendingSize($queueName) : $queue->size($queueName); // @phpstan-ignore function.alreadyNarrowedType (not on the Laravel 11 contract)
            $created = is_callable($oldestCreatedAt) ? $oldestCreatedAt($queueName) : null; // @phpstan-ignore function.alreadyNarrowedType (not on the Laravel 11 contract)
        } catch (Throwable) {
            return ['queue' => $queueName ?? 'default', 'size' => null, 'oldest_seconds' => null];
        }

        return [
            'queue' => $queueName ?? 'default',
            'size' => (int) $size,
            'oldest_seconds' => is_numeric($created) ? max(0, CarbonImmutable::now()->getTimestamp() - (int) $created) : null,
        ];
    }

    /**
     * @param  array{queue: string, size: int|null, oldest_seconds: int|null}  $backlog
     */
    public function isBehind(array $backlog): bool
    {
        $limit = config('vitals.queue_backlog_minutes', 10);

        return $backlog['oldest_seconds'] !== null && $backlog['oldest_seconds'] > (is_numeric($limit) ? (int) $limit : 10) * 60;
    }
}
