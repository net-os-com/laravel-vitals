<?php

namespace NetOs\Vitals\Commands;

use Illuminate\Console\Command;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Vitals;

final class ListCommand extends Command
{
    protected $signature = 'vitals:list';

    protected $description = 'List the registered checks';

    public function handle(Vitals $vitals): int
    {
        $rows = [];

        foreach (Scope::cases() as $scope) {
            foreach ($vitals->checks($scope) as $check) {
                $rows[] = $this->row($check);
            }
        }

        if ($rows === []) {
            $this->components->warn('No checks are registered. Register them with Vitals::central() and Vitals::tenant().');

            return self::SUCCESS;
        }

        $this->table(['Key', 'Label', 'Scope', 'Schedule', 'Timeout', 'Only for'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function row(RegisteredCheck $check): array
    {
        return [
            $check->key(),
            $check->label(),
            $check->scope->value,
            $check->frequency->label(),
            $check->check->timeoutSeconds().'s',
            $check->check->hasTenantCondition() ? 'yes' : '—',
        ];
    }
}
