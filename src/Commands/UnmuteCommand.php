<?php

namespace NetOs\Vitals\Commands;

use Illuminate\Console\Command;
use NetOs\Vitals\Muting\Mutes;

final class UnmuteCommand extends Command
{
    protected $signature = 'vitals:unmute
        {id?* : The ids of the mutes to lift}
        {--reason= : Lift every mute with exactly this reason}
        {--all : Lift every mute}';

    protected $description = 'Lift mutes';

    public function handle(Mutes $mutes): int
    {
        $reason = $this->option('reason');
        /** @var array<int, string> $ids */
        $ids = (array) $this->argument('id');

        $lifted = match (true) {
            (bool) $this->option('all') => $mutes->unmuteAll(),
            is_string($reason) && $reason !== '' => $mutes->unmuteWithReason($reason),
            $ids !== [] => collect($ids)->filter(fn (string $id): bool => $mutes->unmute((int) $id))->count(),
            default => null,
        };

        if ($lifted === null) {
            $this->components->error('Pass mute ids, --reason or --all.');

            return self::FAILURE;
        }

        $this->components->info("Lifted {$lifted} ".str('mute')->plural($lifted).'.');

        return self::SUCCESS;
    }
}
