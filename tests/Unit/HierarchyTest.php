<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Nusantara;
use Nusantara\Tests\TestCase;

class HierarchyTest extends TestCase
{
    public function test_full_hierarchy_from_village_code(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $path = $nusantara->hierarchy('32.73.01.1001');
        $this->assertNotNull($path);
        $this->assertArrayHasKey('province', $path);
        $this->assertArrayHasKey('regency', $path);
        $this->assertArrayHasKey('district', $path);
        $this->assertArrayHasKey('village', $path);
        $this->assertEquals('JAWA BARAT', $path['province']['name'] ?? '');
        $this->assertEquals('CIHAPIT', $path['village']['name'] ?? '');
    }

    public function test_hierarchy_from_district_code(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $path = $nusantara->hierarchy('32.73.01');
        $this->assertNotNull($path);
        $this->assertArrayHasKey('province', $path);
        $this->assertArrayHasKey('regency', $path);
        $this->assertArrayHasKey('district', $path);
        $this->assertArrayNotHasKey('village', $path);
    }

    public function test_hierarchy_from_regency_code(): void
    {
        $nusantara = $this->app->make(Nusantara::class);
        $path = $nusantara->hierarchy('32.73');
        $this->assertNotNull($path);
        $this->assertEquals('KOTA BANDUNG', $path['regency']['name'] ?? '');
    }
}
