<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Support\Cache;
use Nusantara\Tests\TestCase;

class CacheTest extends TestCase
{
    public function test_key_provinces_returns_prefixed_key(): void
    {
        $this->assertSame('nusantara:provinces', Cache::keyProvinces());
    }

    public function test_key_regencies_returns_prefixed_key_with_code(): void
    {
        $this->assertSame('nusantara:regencies:32', Cache::keyRegencies('32'));
    }

    public function test_key_districts_returns_prefixed_key_with_code(): void
    {
        $this->assertSame('nusantara:districts:32.73', Cache::keyDistricts('32.73'));
    }

    public function test_key_villages_returns_prefixed_key_with_code(): void
    {
        $this->assertSame('nusantara:villages:32.73.01', Cache::keyVillages('32.73.01'));
    }

    public function test_key_postal_returns_prefixed_key_with_code(): void
    {
        $this->assertSame('nusantara:postal:40132', Cache::keyPostal('40132'));
    }

    public function test_key_search_returns_prefixed_key_with_hash(): void
    {
        $this->assertSame('nusantara:search:abc', Cache::keySearch('abc'));
    }

    public function test_get_with_zero_ttl_calls_callback_and_does_not_use_cache(): void
    {
        $this->app['config']->set('nusantara.cache_ttl', 0);

        $cache = new Cache();
        $callCount = 0;
        $result = $cache->get('test-key', function () use (&$callCount) {
            $callCount++;
            return ['data' => 'value'];
        });

        $this->assertSame(1, $callCount);
        $this->assertSame(['data' => 'value'], $result);
    }

    public function test_get_with_positive_ttl_uses_remember(): void
    {
        $this->app['config']->set('nusantara.cache_ttl', 60);

        $cache = new Cache();
        $result = $cache->get('provinces', fn () => [['code' => '32', 'name' => 'JAWA BARAT']]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('32', $result[0]['code']);
    }

    public function test_forget_removes_key(): void
    {
        $this->app['config']->set('nusantara.cache_ttl', 60);
        $cache = new Cache();
        $cache->get('forget-me', fn () => 'value');
        $forgotten = $cache->forget('forget-me');
        $this->assertTrue($forgotten);
    }

    public function test_flush_succeeds_without_exception(): void
    {
        $cache = new Cache();
        $result = $cache->flush();
        $this->assertTrue($result);
    }
}
