<?php

namespace NetOs\Vitals\Support;

/**
 * The dashboard serves its CSS and JS inline, so the host app needs no published assets.
 */
final class Assets
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function css(): string
    {
        return self::read('vitals.css');
    }

    public static function js(): string
    {
        return self::read('vitals.js');
    }

    private static function read(string $file): string
    {
        return self::$cache[$file] ??= (string) file_get_contents(__DIR__.'/../../resources/dist/'.$file);
    }
}
