<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Nusantara;
use Nusantara\Tests\TestCase;

class NusantaraTest extends TestCase
{
    public function test_find_province_by_code(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $region = $nusantara->find('32');
        $this->assertNotNull($region);
        $this->assertSame('32', $region['code']);
        $this->assertSame('JAWA BARAT', $region['name']);
    }

    public function test_find_regency_by_code(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $region = $nusantara->find('32.73');
        $this->assertNotNull($region);
        $this->assertSame('32.73', $region['code']);
        $this->assertStringContainsString('BANDUNG', $region['name']);
    }

    public function test_find_district_by_code(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $region = $nusantara->find('32.73.01');
        $this->assertNotNull($region);
        $this->assertSame('32.73.01', $region['code']);
    }

    public function test_find_village_by_code(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $region = $nusantara->find('32.73.01.1001');
        $this->assertNotNull($region);
        $this->assertSame('32.73.01.1001', $region['code']);
        $this->assertSame('CIHAPIT', $region['name']);
    }

    public function test_find_invalid_code_returns_null(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $this->assertNull($nusantara->find('99.99.99.9999'));
        $this->assertNull($nusantara->find('99'));
    }

    public function test_find_normalizes_code_with_spaces(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $region = $nusantara->find('32 . 73 . 01 . 1001');
        $this->assertNotNull($region);
        $this->assertSame('32.73.01.1001', $region['code']);
    }

    public function test_regencies_by_province_code(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $regencies = $nusantara->regencies('32');
        $this->assertGreaterThan(0, $regencies->count());
        $this->assertSame('32.73', $regencies->first()['code']);
    }

    public function test_regencies_by_province_name(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $regencies = $nusantara->regencies('JAWA BARAT');
        $this->assertGreaterThan(0, $regencies->count());
    }

    public function test_regencies_empty_input_returns_empty_collection(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $regencies = $nusantara->regencies('');
        $this->assertSame(0, $regencies->count());
    }

    public function test_postal_codes_returns_array(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $codes = $nusantara->postalCodes('32.73');
        $this->assertIsArray($codes);
        if (count($codes) > 0) {
            foreach ($codes as $code) {
                $this->assertTrue(is_string($code) || is_int($code), 'Postal codes may be string or int from data');
            }
        }
    }

    public function test_hierarchy_invalid_code_returns_null(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $path = $nusantara->hierarchy('99.99.99.9999');
        $this->assertNull($path);
    }

    public function test_hierarchy_partial_code_returns_partial_structure(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $path = $nusantara->hierarchy('32');
        $this->assertNotNull($path);
        $this->assertArrayHasKey('province', $path);
        $this->assertArrayNotHasKey('regency', $path);
    }

    public function test_coordinates_returns_latitude_longitude_structure(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $coords = $nusantara->coordinates('32.73');
        $this->assertArrayHasKey('latitude', $coords);
        $this->assertArrayHasKey('longitude', $coords);
        $this->assertTrue(array_key_exists('latitude', $coords) && array_key_exists('longitude', $coords));
    }

    public function test_coordinates_invalid_code_returns_nulls(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $coords = $nusantara->coordinates('99.99.99');
        $this->assertSame(['latitude' => null, 'longitude' => null], $coords);
    }

    public function test_coordinates_empty_code_returns_nulls(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $coords = $nusantara->coordinates('');
        $this->assertSame(['latitude' => null, 'longitude' => null], $coords);
    }

    public function test_districts_returns_collection(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $districts = $nusantara->districts('32.73');
        $this->assertGreaterThan(0, $districts->count());
    }

    public function test_villages_returns_collection(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $villages = $nusantara->villages('32.73.01');
        $this->assertGreaterThan(0, $villages->count());
    }
}
