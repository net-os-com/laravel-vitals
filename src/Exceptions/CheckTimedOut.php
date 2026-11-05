<?php

namespace NetOs\Vitals\Exceptions;

use RuntimeException;

final class CheckTimedOut extends RuntimeException
{
    public static function after(int $seconds): self
    {
        return new self("Check timed out after {$seconds} seconds");
    }
}
