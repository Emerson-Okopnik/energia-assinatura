<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Cache;

trait CachesFindAll
{
    /**
     * Cache TTL in seconds (5 minutes by default)
     */
    private int $findAllCacheTtl = 300;

    /**
     * Remember the result of findAll queries with caching
     *
     * @param string $cacheKey
     * @param callable $callback
     * @return array
     */
    protected function rememberFindAll(string $cacheKey, callable $callback): array
    {
        return Cache::remember($cacheKey, $this->findAllCacheTtl, function () use ($callback) {
            $result = $callback();
            return is_array($result) ? $result : $result->toArray();
        });
    }

    /**
     * Forget (invalidate) the findAll cache
     *
     * @param string $cacheKey
     * @return void
     */
    protected function forgetFindAllCache(string $cacheKey): void
    {
        Cache::forget($cacheKey);
    }

    /**
     * Set custom TTL for findAll cache
     *
     * @param int $seconds
     * @return void
     */
    protected function setFindAllCacheTtl(int $seconds): void
    {
        $this->findAllCacheTtl = $seconds;
    }
}
