<?php

namespace NetOs\Vitals;

use Closure;
use Illuminate\Support\Str;
use NetOs\Vitals\Checks\Concerns\ManagesFrequencies;
use NetOs\Vitals\Tenancy\TenantReference;

abstract class Check
{
    use ManagesFrequencies;

    protected ?string $key = null;

    protected ?string $label = null;

    protected int $timeout = 30;

    /** @var (Closure(TenantReference): bool)|null */
    protected ?Closure $onlyFor = null;

    abstract public function run(): Result;

    public static function new(mixed ...$arguments): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$arguments);
    }

    public function key(): string
    {
        return $this->key ?? Str::of(class_basename(static::class))->beforeLast('Check')->kebab()->toString();
    }

    public function withKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function label(): string
    {
        return $this->label ?? Str::of($this->key())->replace('-', ' ')->ucfirst()->toString();
    }

    public function withLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeout;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Only run this tenant check for tenants that pass the callback. The others show it as "Not applicable".
     *
     * @param  Closure(TenantReference): bool  $callback
     */
    public function onlyFor(Closure $callback): static
    {
        $this->onlyFor = $callback;

        return $this;
    }

    public function appliesTo(TenantReference $tenant): bool
    {
        return $this->onlyFor === null || ($this->onlyFor)($tenant);
    }

    public function hasTenantCondition(): bool
    {
        return $this->onlyFor !== null;
    }
}
