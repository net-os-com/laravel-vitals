<?php

namespace NetOs\Vitals\Tenancy;

final readonly class TenantReference
{
    public function __construct(
        public string $key,
        public string $name,
        public ?string $domain = null,
        public mixed $model = null,
    ) {}
}
