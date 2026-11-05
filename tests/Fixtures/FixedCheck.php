<?php

namespace NetOs\Vitals\Tests\Fixtures;

use Closure;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

/**
 * Returns whatever the callback returns, so a test can control each run.
 */
final class FixedCheck extends Check
{
    /** @var Closure(): Result */
    private Closure $callback;

    /**
     * @param  Result|Closure(): Result  $result
     */
    public function __construct(Result|Closure $result)
    {
        $this->callback = $result instanceof Result ? fn (): Result => $result : $result;
    }

    public function run(): Result
    {
        return ($this->callback)();
    }
}
