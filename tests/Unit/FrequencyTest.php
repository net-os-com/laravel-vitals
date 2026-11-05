<?php

use Carbon\CarbonImmutable;
use NetOs\Vitals\Scheduling\Frequency;

it('knows its interval and when it goes stale', function (string $expression, int $interval, string $label) {
    $frequency = new Frequency($expression);

    expect($frequency->intervalMinutes())->toBe($interval)
        ->and($frequency->staleAfterMinutes())->toBe($interval * 2 + 5)
        ->and($frequency->label())->toBe($label);
})->with([
    ['* * * * *', 1, 'Every minute'],
    ['*/5 * * * *', 5, 'Every 5 minutes'],
    ['0 * * * *', 60, 'Hourly'],
    ['0 6 * * *', 1440, 'Daily at 06:00'],
]);

it('is due on matching minutes', function () {
    $frequency = new Frequency('*/15 * * * *');

    expect($frequency->isDue(CarbonImmutable::parse('2026-11-05 10:30:20')))->toBeTrue()
        ->and($frequency->isDue(CarbonImmutable::parse('2026-11-05 10:31:00')))->toBeFalse();
});

it('spreads tenants over the interval with a stable jitter', function () {
    $frequency = new Frequency('*/15 * * * *');
    $start = CarbonImmutable::parse('2026-11-05 10:00:00');

    $dueMinutes = fn (string $tenant): array => collect(range(0, 59))
        ->filter(fn (int $minute): bool => $frequency->isDue($start->addMinutes($minute), $tenant))
        ->values()
        ->all();

    $offsets = collect(['acme', 'globex', 'initech', 'hooli', 'umbrella'])
        ->map(fn (string $tenant): int => $frequency->jitterMinutes($tenant));

    expect($dueMinutes('acme'))->toHaveCount(4)
        ->and($dueMinutes('acme')[0])->toBe($frequency->jitterMinutes('acme'))
        ->and($offsets->unique()->count())->toBeGreaterThan(1)
        ->and($frequency->jitterMinutes('acme'))->toBe($frequency->jitterMinutes('acme'));
});
