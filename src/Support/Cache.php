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

        return $this->store->remember($fullKey, $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        return $this->store->forget(self::PREFIX . $key);
    }

    public function flush(): bool
    {
        $this->store->forget(self::PREFIX . 'provinces');
        return true;
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
