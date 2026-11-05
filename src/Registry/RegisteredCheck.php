<?php

namespace NetOs\Vitals\Registry;

use NetOs\Vitals\Check;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Scheduling\Frequency;

final readonly class RegisteredCheck
{
    public function __construct(
        public Check $check,
        public Scope $scope,
        public Frequency $frequency,
    ) {}

    public function key(): string
    {
        return $this->check->key();
    }

    public function label(): string
    {
        return $this->check->label();
    }
}
