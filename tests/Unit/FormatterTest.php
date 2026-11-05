<?php

use Carbon\CarbonImmutable;
use NetOs\Vitals\Support\Formatter;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-11-05 14:32:00'));

it('formats dates relative to today', function (string $moment, string $expected) {
    expect(Formatter::dateTime(CarbonImmutable::parse($moment)))->toBe($expected);
})->with([
    ['2026-11-05 09:15:00', 'Today, 09:15'],
    ['2026-11-04 23:00:00', 'Yesterday, 23:00'],
    ['2026-11-06 06:00:00', 'Tomorrow, 06:00'],
    ['2026-11-03 11:15:00', 'Tue, 3 Nov, 11:15'],
    ['2026-07-14 08:00:00', 'Tue, 14 July, 08:00'],
    ['2025-06-02 08:00:00', 'Mon, 2 June 2025, 08:00'],
]);

it('formats how long ago something happened', function (int $seconds, string $expected) {
    expect(Formatter::ago(CarbonImmutable::now()->subSeconds($seconds)))->toBe($expected);
})->with([
    [3, 'just now'],
    [34, '34 seconds ago'],
    [9 * 60, '9 min ago'],
    [7 * 3600, '7 h ago'],
]);
