<?php

namespace NetOs\Vitals\Exceptions;

use RuntimeException;
use Throwable;

final class TenantNotInitialized extends RuntimeException
{
    public const string PREFIX = 'Tenant could not be initialized';

    public static function because(Throwable $previous): self
    {
        return new self(self::PREFIX.': '.$previous->getMessage(), 0, $previous);
    }
}
