<?php

namespace NetOs\Vitals\Checks;

use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class EnvironmentCheck extends Check
{
    protected string $expected = 'production';

    public function expect(string $environment): static
    {
        $this->expected = $environment;

        return $this;
    }

    public function run(): Result
    {
        $environment = (string) app()->environment();

        return $environment === $this->expected
            ? Result::ok("Running in {$environment}")
            : Result::failed("Running in {$environment}, expected {$this->expected}");
    }
}
