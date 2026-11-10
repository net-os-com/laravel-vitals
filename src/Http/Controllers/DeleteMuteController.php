<?php

namespace NetOs\Vitals\Http\Controllers;

use Illuminate\Http\Response;
use NetOs\Vitals\Muting\Mutes;

final readonly class DeleteMuteController
{
    public function __construct(private Mutes $mutes) {}

    public function __invoke(int $mute): Response
    {
        abort_unless($this->mutes->unmute($mute), 404);

        return response()->noContent();
    }
}
