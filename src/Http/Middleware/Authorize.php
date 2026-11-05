<?php

namespace NetOs\Vitals\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NetOs\Vitals\Vitals;
use Symfony\Component\HttpFoundation\Response;

final readonly class Authorize
{
    public function __construct(private Vitals $vitals) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        if ($mode === 'token' && $this->hasValidToken($request)) {
            return $next($request);
        }

        abort_unless($this->vitals->check($request), 403);

        return $next($request);
    }

    private function hasValidToken(Request $request): bool
    {
        $token = config('vitals.status_token');
        $given = $request->header('X-Vitals-Token');

        return is_string($token) && $token !== '' && is_string($given) && hash_equals($token, $given);
    }
}
