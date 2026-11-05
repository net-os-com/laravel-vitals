<?php

namespace NetOs\Vitals\Checks;

use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class DebugModeCheck extends Check
{
    protected bool $expected = false;

    public function expectedToBe(bool $enabled): static
    {
        $this->expected = $enabled;

        return $this;
    }

    public function run(): Result
    {
        $enabled = (bool) config('app.debug');
        $summary = $enabled ? 'Debug mode is on' : 'Debug mode is off';

        return $enabled === $this->expected
            ? Result::ok($summary)
            : Result::failed($summary)->meta(['expected' => $this->expected ? 'on' : 'off']);
    }
}
