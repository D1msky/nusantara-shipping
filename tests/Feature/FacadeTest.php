<?php

namespace Nusantara\Tests\Feature;

use Nusantara\NusantaraFacade as Nusantara;
use Nusantara\Tests\TestCase;

class FacadeTest extends TestCase
{
    public function test_facade_resolves_correctly(): void
    {
        $this->assertInstanceOf(\Nusantara\Nusantara::class, Nusantara::getFacadeRoot());
    }

    public function test_provinces_returns_collection(): void
    {
        $provinces = Nusantara::provinces();
        $this->assertInstanceOf(\Nusantara\Support\RegionCollection::class, $provinces);
        $this->assertGreaterThan(0, $provinces->count());
    }

    public function test_search_returns_results(): void
    {
        $results = Nusantara::search('bandung');
        $this->assertGreaterThan(0, $results->count());
    }

    public function test_shipping_address_formats_correctly(): void
    {
        $addr = Nusantara::shippingAddress('32.73.01.1001');
        $this->assertNotNull($addr);
        $this->assertStringContainsString('Cihapit', $addr);
    }

    public function test_nearest_regency_returns_result(): void
    {
        $nearest = Nusantara::nearestRegency(-6.2088, 106.8456);
        $this->assertNotNull($nearest);
        $this->assertArrayHasKey('code', $nearest);
        $this->assertArrayHasKey('name', $nearest);
        $this->assertArrayHasKey('distance_km', $nearest);
    }
}
