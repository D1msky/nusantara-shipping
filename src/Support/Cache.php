<?php

declare(strict_types=1);

namespace Nusantara\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache as CacheFacade;

final class Cache
{
    private const PREFIX = 'nusantara:';

    private CacheRepository $store;

    /** @var array<int, string> track all keys written so flush can clear them */
    private static array $trackedKeys = [];

    public function __construct()
    {
        $storeName = config('nusantara.cache_store');
        $this->store = $storeName !== null && $storeName !== ''
            ? CacheFacade::store($storeName)
            : CacheFacade::store();
    }

    public function get(string $key, callable $callback): mixed
    {
        $fullKey = self::PREFIX . $key;
        $ttl = (int) config('nusantara.cache_ttl', 86400);

        if ($ttl <= 0) {
            return $callback();
        }

        self::trackKey($fullKey);

        return $this->store->remember($fullKey, $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        $fullKey = self::PREFIX . $key;
        self::removeTrackedKey($fullKey);

        return $this->store->forget($fullKey);
    }

    public function flush(): bool
    {
        // Flush the registry key first to get all tracked keys
        $registryKey = self::PREFIX . '_keys';
        $keys = $this->store->get($registryKey, []);

        if (is_array($keys)) {
            foreach ($keys as $key) {
                $this->store->forget($key);
            }
        }

        // Also flush any statically tracked keys from this process
        foreach (self::$trackedKeys as $key) {
            $this->store->forget($key);
        }

        // Clear the registry and static tracker
        $this->store->forget($registryKey);
        self::$trackedKeys = [];

        return true;
    }

    private static function trackKey(string $fullKey): void
    {
        if (! in_array($fullKey, self::$trackedKeys, true)) {
            self::$trackedKeys[] = $fullKey;
        }
    }

    private static function removeTrackedKey(string $fullKey): void
    {
        self::$trackedKeys = array_values(array_filter(
            self::$trackedKeys,
            fn (string $k) => $k !== $fullKey,
        ));
    }

    /**
     * Persist tracked keys to cache so flush() works across requests.
     */
    public function persistKeyRegistry(): void
    {
        $registryKey = self::PREFIX . '_keys';
        $ttl = (int) config('nusantara.cache_ttl', 86400);
        if ($ttl > 0) {
            $existing = $this->store->get($registryKey, []);
            $merged = array_unique(array_merge(is_array($existing) ? $existing : [], self::$trackedKeys));
            $this->store->put($registryKey, $merged, $ttl * 2);
        }
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
