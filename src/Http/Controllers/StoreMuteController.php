<?php

namespace NetOs\Vitals\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Muting\MuteDuration;
use NetOs\Vitals\Muting\Mutes;
use NetOs\Vitals\Tenancy\TenancyAdapter;
use NetOs\Vitals\Vitals;

final readonly class StoreMuteController
{
    public function __construct(
        private Vitals $vitals,
        private TenancyAdapter $tenancy,
        private Mutes $mutes,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::enum(Scope::class)],
            'check' => ['nullable', 'string'],
            'tenant' => ['nullable', 'string'],
            'duration' => ['required', Rule::enum(MuteDuration::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $scope = Scope::from($data['scope']);
        $check = $data['check'] ?? null;
        $tenant = $scope === Scope::Central ? null : ($data['tenant'] ?? null);

        $this->ensureTargetExists($scope, $check, $tenant);

        $mute = $this->mutes->mute($scope, $check, $tenant, MuteDuration::from($data['duration']), $data['reason'] ?? null);

        return response()->json(['id' => $mute->id, 'description' => $mute->description()], 201);
    }

    private function ensureTargetExists(Scope $scope, ?string $check, ?string $tenant): void
    {
        if ($check === null && ($scope === Scope::Central || $tenant === null)) {
            throw ValidationException::withMessages(['check' => 'Choose a check, a tenant or both.']);
        }

        if ($check !== null && $this->vitals->find($scope, $check) === null) {
            throw ValidationException::withMessages(['check' => "There is no {$scope->value} check [{$check}]."]);
        }

        if ($tenant !== null && $this->tenancy->find($tenant) === null) {
            throw ValidationException::withMessages(['tenant' => "There is no tenant [{$tenant}]."]);
        }
    }
}
