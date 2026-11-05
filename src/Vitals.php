<?php

namespace NetOs\Vitals;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use NetOs\Vitals\Enums\Scope;
use NetOs\Vitals\Exceptions\DuplicateCheckKey;
use NetOs\Vitals\Registry\RegisteredCheck;
use NetOs\Vitals\Scheduling\Frequency;

class Vitals
{
    /** @var array<string, array<string, RegisteredCheck>> */
    private array $checks = ['central' => [], 'tenant' => []];

    /** @var (Closure(Request): bool)|null */
    private ?Closure $authUsing = null;

    /**
     * @param  array<string, string>  $defaultFrequencies  scope value => cron expression
     */
    public function __construct(private readonly array $defaultFrequencies = []) {}

    /**
     * @param  array<int, Check>  $checks
     */
    public function central(array $checks): static
    {
        return $this->register(Scope::Central, $checks);
    }

    /**
     * @param  array<int, Check>  $checks
     */
    public function tenant(array $checks): static
    {
        return $this->register(Scope::Tenant, $checks);
    }

    /**
     * @param  Closure(Request): bool  $callback
     */
    public function auth(Closure $callback): static
    {
        $this->authUsing = $callback;

        return $this;
    }

    /**
     * The auth callback when set, otherwise the viewVitals gate, otherwise only the local environment.
     */
    public function check(Request $request): bool
    {
        if ($this->authUsing !== null) {
            return ($this->authUsing)($request);
        }

        if (Gate::has('viewVitals')) {
            return Gate::forUser($request->user())->allows('viewVitals');
        }

        return app()->environment('local');
    }

    /**
     * @return Collection<int, RegisteredCheck>
     */
    public function checks(Scope $scope): Collection
    {
        return new Collection(array_values($this->checks[$scope->value]));
    }

    public function find(Scope $scope, string $key): ?RegisteredCheck
    {
        return $this->checks[$scope->value][$key] ?? null;
    }

    public function flush(): void
    {
        $this->checks = ['central' => [], 'tenant' => []];
        $this->authUsing = null;
    }

    /**
     * @param  array<int, Check>  $checks
     */
    private function register(Scope $scope, array $checks): static
    {
        foreach ($checks as $check) {
            $key = $check->key();

            if (isset($this->checks[$scope->value][$key])) {
                throw DuplicateCheckKey::make($key, $scope);
            }

            $expression = $check->expression() ?? $this->defaultFrequencies[$scope->value] ?? '*/5 * * * *';

            $this->checks[$scope->value][$key] = new RegisteredCheck($check, $scope, new Frequency($expression));
        }

        return $this;
    }
}
