<?php

namespace NetOs\Vitals\Dashboard;

use NetOs\Vitals\Models\CheckChange;

final readonly class ChangeEntry
{
    public function __construct(
        public CheckChange $change,
        public string $checkLabel,
        public ?string $tenantName,
        public string $url,
    ) {}

    public function isRecovery(): bool
    {
        return $this->change->to_status->isHealthy();
    }
}
