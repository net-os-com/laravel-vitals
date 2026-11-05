<?php

namespace NetOs\Vitals\Models\Concerns;

/**
 * Vitals always writes to the central database, also while a tenant is initialized.
 */
trait UsesVitalsConnection
{
    public function getConnectionName(): ?string
    {
        $connection = config('vitals.connection');

        return is_string($connection) ? $connection : null;
    }
}
