<?php

namespace NetOs\Vitals\Facades;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use NetOs\Vitals\Check;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Registry\RegisteredCheck;

/**
 * @method static \NetOs\Vitals\Vitals central(array<int, Check> $checks)
 * @method static \NetOs\Vitals\Vitals tenant(array<int, Check> $checks)
 * @method static \NetOs\Vitals\Vitals auth(Closure(Request): bool $callback)
 * @method static bool check(Request $request)
 * @method static Collection<int, RegisteredCheck> checks(Scope $scope)
 * @method static RegisteredCheck|null find(Scope $scope, string $key)
 * @method static void flush()
 *
 * @see \NetOs\Vitals\Vitals
 */
class Vitals extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \NetOs\Vitals\Vitals::class;
    }
}
