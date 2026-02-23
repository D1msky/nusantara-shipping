<?php

declare(strict_types=1);

namespace Nusantara\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache as CacheFacade;

final class Cache
{
    private const PREFIX = 'nusantara:';

    private ?CacheRepository $store = null;

    public function __construct()
    {
        $store = config('nusantara.cache_store');
        $this->store = $store !== null && $store !== ''
            ? CacheFacade::store($store)
            : CacheFacade::store();
    }

    public function get(string $key, callable $callback): mixed
    {
        $fullKey = self::PREFIX . $key;
        $ttl = (int) config('nusantara.cache_ttl', 86400);

        if ($ttl <= 0) {
            return $callback();
        }

        try {
            if ($this->supportsTags()) {
                return $this->store->tags(['nusantara'])->remember($fullKey, $ttl, $callback);
            }
        } catch (\BadMethodCallException $e) {
            // Store has tags() but driver does not support tagging (e.g. database, file)
        }

        return $this->store->remember($fullKey, $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        $fullKey = self::PREFIX . $key;
        try {
            if ($this->supportsTags()) {
                return $this->store->tags(['nusantara'])->forget($fullKey);
            }
        } catch (\BadMethodCallException $e) {
            // Store does not support tagging
        }
        return $this->store->forget($fullKey);
    }

    public function flush(): bool
    {
        try {
            if ($this->supportsTags()) {
                $this->store->tags(['nusantara'])->flush();
                return true;
            }
        } catch (\BadMethodCallException $e) {
            // Store does not support tagging
        }

        $this->flushFallback();

        return true;
    }

    private function supportsTags(): bool
    {
        return method_exists($this->store, 'tags');
    }

    /**
     * Fallback when store does not support tags (e.g. file, database): clear known key patterns.
     * Caller may have used dynamic keys (e.g. regencies:32); we only clear the static "provinces" key.
     * For full flush, use a taggable cache store (Redis, Memcached) or restart cache.
     */
    private function flushFallback(): void
    {
        $this->store->forget(self::PREFIX . 'provinces');
    }

    public static function keyProvinces(): string
    {
        return self::PREFIX . 'provinces';
    }

    public static function keyRegencies(string $code): string
    {
        return self::PREFIX . 'regencies:' . $code;
    }

    public static function keyDistricts(string $code): string
    {
        return self::PREFIX . 'districts:' . $code;
    }

    public static function keyVillages(string $code): string
    {
        return self::PREFIX . 'villages:' . $code;
    }

    public static function keyPostal(string $code): string
    {
        return self::PREFIX . 'postal:' . $code;
    }

    public static function keySearch(string $hash): string
    {
        return self::PREFIX . 'search:' . $hash;
    }
}
