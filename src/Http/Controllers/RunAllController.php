<?php

namespace NetOs\Vitals\Http\Controllers;

use Illuminate\Http\JsonResponse;
use NetOs\Vitals\Scheduling\Dispatcher;

final readonly class RunAllController
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(['dispatched' => $this->dispatcher->dispatchAll()], 202);
    }
}
