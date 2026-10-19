<?php

namespace Nusantara\Tests\Unit;

use Nusantara\Data\FileRepository;
use Nusantara\Tests\TestCase;

class FileRepositoryTest extends TestCase
{
    private FileRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FileRepository();
    }

    public function test_can_load_all_provinces(): void
    {
        $provinces = $this->repo->provinces();
        $this->assertGreaterThan(0, $provinces->count());
        $first = $provinces->first();
        $this->assertArrayHasKey('code', $first);
        $this->assertArrayHasKey('name', $first);
    }

    public function test_can_load_regencies_by_province_code(): void
    {
        $regencies = $this->repo->regencies('32');
        $this->assertGreaterThan(0, $regencies->count());
        // Data is ordered by code; 32.01 (Kabupaten Bogor) is the first regency in province 32
        $this->assertEquals('32.01', $regencies->first()['code'] ?? null);
        // Ensure a well-known regency (Kota Bandung) is present in the collection
        $codes = array_column($regencies->all(), 'code');
        $this->assertContains('32.73', $codes);
    }

    public function test_can_load_districts_by_regency_code(): void
    {
        $districts = $this->repo->districts('32.73');
        $this->assertGreaterThan(0, $districts->count());
        $this->assertEquals('32.73.01', $districts->first()['code'] ?? null);
    }

    public function test_can_load_villages_by_district_code(): void
    {
        $villages = $this->repo->villages('32.73.01');
        $this->assertGreaterThan(0, $villages->count());
        $this->assertEquals('32.73.01.1001', $villages->first()['code'] ?? null);
    }

    public function test_find_province_by_code(): void
    {
        $p = $this->repo->findProvince('32');
        $this->assertNotNull($p);
        $this->assertEquals('32', $p['code']);
        $this->assertEquals('JAWA BARAT', $p['name']);
    }

    public function test_find_returns_null_for_invalid_code(): void
    {
        $this->assertNull($this->repo->findProvince('99'));
        $this->assertNull($this->repo->findRegency('99.99'));
    }

    public function test_search_by_postal_code(): void
    {
        $results = $this->repo->findByPostalCode('40132');
        $this->assertGreaterThan(0, $results->count());
        $first = $results->first();
        $this->assertEquals('40132', $first['postal_code'] ?? null);
    }

    public function test_reads_legacy_array_villages_file_from_custom_data_path(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nusantara-legacy-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'villages.php', "<?php return [\n"
            . "    ['code' => '32.73.01.1001', 'district_code' => '32.73.01', 'name' => 'CIHAPIT', 'postal_code' => '40114'],\n"
            . "];\n");
        $this->app['config']->set('nusantara.data_path', $dir);

        $repo = new FileRepository();
        $this->assertSame('CIHAPIT', $repo->findVillage('32.73.01.1001')['name'] ?? null);
        $this->assertSame(['32.73.01.1001'], $repo->findByPostalCode('40114')->codes());

        $this->app['files']->deleteDirectory($dir);
    }
}
