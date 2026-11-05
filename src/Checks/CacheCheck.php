<?php

namespace NetOs\Vitals\Checks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class CacheCheck extends Check
{
    protected ?string $store = null;

    public function store(string $store): static
    {
        $this->store = $store;

        return $this;
    }

    public function run(): Result
    {
        $cache = Cache::store($this->store);
        $key = 'vitals:cache-check:'.Str::random(8);
        $value = Str::random(16);

        $cache->put($key, $value, 10);
        $read = $cache->get($key);
        $cache->forget($key);

        if ($read !== $value) {
            return Result::failed('Could not read back a value that was just written');
        }

        return Result::ok('Write, read and delete succeeded')->meta([
            'store' => $this->store ?? config('cache.default'),
        ]);
    }
}
