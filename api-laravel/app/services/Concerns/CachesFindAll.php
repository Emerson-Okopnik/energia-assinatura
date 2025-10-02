<?php

namespace App\Services\Concerns;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait CachesFindAll
{
    protected int $findAllCacheTtl = 300;

    /**
     * @template T
     * @param  Closure():Collection|array  $callback
     */
    protected function rememberFindAll(string $cacheKey, Closure $callback): array
    {
        return Cache::remember($cacheKey, $this->findAllCacheTtl, function () use ($callback) {
            $result = $callback();

            if ($result instanceof Collection) {
                return $result->toArray();
            }

            return is_array($result) ? $result : [];
        });
    }

    protected function forgetFindAllCache(string $cacheKey): void
    {
        Cache::forget($cacheKey);
    }
}