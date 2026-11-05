<?php

namespace NetOs\Vitals\Enums;

enum Scope: string
{
    case Central = 'central';
    case Tenant = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::Central => 'Central check',
            self::Tenant => 'Tenant check',
        };
    }
}
