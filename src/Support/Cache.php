<?php

declare(strict_types=1);

namespace Nusantara\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache as CacheFacade;

final class Cache
{
    private const PREFIX = 'nusantara:';

    private const VERSION_KEY = self::PREFIX . 'version';

    private CacheRepository $store;

    private ?string $versionedPrefix = null;

    /** @var array<string, mixed> in-process memo so repeated lookups skip the cache store round-trip */
    private array $memo = [];

    public function __construct()
    {
        $store = config('nusantara.cache_store');
        $this->store = $store !== null && $store !== ''
            ? CacheFacade::store($store)
            : CacheFacade::store();
    }

    public function get(string $key, callable $callback): mixed
    {
        $ttl = (int) config('nusantara.cache_ttl', 86400);

        if ($ttl <= 0) {
            return $callback();
        }

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->store->remember($this->prefix() . $key, $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        unset($this->memo[$key]);

        return $this->store->forget($this->prefix() . $key);
    }

    /**
     * Invalidate every Nusantara entry by bumping the key version. Works on every cache store
     * (file, database, redis, ...) without tags; orphaned entries expire through their TTL.
     */
    public function flush(): bool
    {
        $this->store->forever(self::VERSION_KEY, $this->version() + 1);
        $this->versionedPrefix = null;
        $this->memo = [];

        return true;
    }

    private function prefix(): string
    {
        return $this->versionedPrefix ??= self::PREFIX . 'v' . $this->version() . ':';
    }

    private function version(): int
    {
        return (int) $this->store->get(self::VERSION_KEY, 1);
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
