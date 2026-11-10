<?php

use NetOs\Vitals\Dashboard\Flapping;
use NetOs\Vitals\Enums\Status;

it('counts switches between healthy and unhealthy within the window', function () {
    $ok = Status::Ok;
    $failed = Status::Failed;

    expect(Flapping::changes([$ok, $failed, $ok, $failed, $ok]))->toBe(4)
        ->and(Flapping::detect([$ok, $failed, $ok, $failed, $ok]))->toBeTrue()
        ->and(Flapping::changes([$ok, Status::Skipped, $ok]))->toBe(0)
        ->and(Flapping::changes([$failed, Status::Crashed, Status::Warning]))->toBe(0)
        ->and(Flapping::detect([...array_fill(0, 12, $ok), $failed, $ok, $failed, $ok, $failed]))->toBeFalse();
});
