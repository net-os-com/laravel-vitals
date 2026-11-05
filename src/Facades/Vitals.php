<?php

namespace NetOs\Vitals\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \NetOs\Vitals\Vitals
 */
class Vitals extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \NetOs\Vitals\Vitals::class;
    }
}
