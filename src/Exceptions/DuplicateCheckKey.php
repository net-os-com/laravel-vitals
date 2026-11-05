<?php

namespace NetOs\Vitals\Exceptions;

use InvalidArgumentException;
use NetOs\Vitals\Enums\Scope;

final class DuplicateCheckKey extends InvalidArgumentException
{
    public static function make(string $key, Scope $scope): self
    {
        return new self("A {$scope->value} check with key [{$key}] is already registered. Give one of them a unique key with ->withKey().");
    }
}
