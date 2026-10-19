<?php

namespace Nusantara\Tests\Feature;

use Nusantara\Data\VillageIndex;
use Nusantara\Nusantara;
use Nusantara\Tests\TestCase;

class UpdateDataCommandTest extends TestCase
{
    private string $baseDir;

    private string $sourceDir;

    private string $targetDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nusantara-update-' . uniqid();
        $this->sourceDir = $this->baseDir . DIRECTORY_SEPARATOR . 'source';
        $this->targetDir = $this->baseDir . DIRECTORY_SEPARATOR . 'data';
        mkdir($this->sourceDir, 0755, true);
        $this->app['config']->set('nusantara.data_path', $this->targetDir);
    }

    protected function tearDown(): void
    {
        $this->app['files']->deleteDirectory($this->baseDir);
        parent::tearDown();
    }

    private function writeJson(string $name, array $rows): void
    {
        file_put_contents($this->sourceDir . DIRECTORY_SEPARATOR . $name . '.json', json_encode($rows));
    }

    public function test_converts_local_json_into_loadable_data_files(): void
    {
        $this->writeJson('provinces', [['id' => '32', 'nama' => 'Jawa Barat', 'lat' => -6.9, 'lng' => 107.6]]);
        $this->writeJson('regencies', [['id' => '3273', 'province_id' => '32', 'name' => 'Kota Bandung']]);

        $this->artisan('nusantara:update-data', ['--source' => $this->sourceDir])->assertSuccessful();

        $this->assertSame(
            [['code' => '32', 'name' => 'JAWA BARAT', 'latitude' => -6.9, 'longitude' => 107.6]],
            require $this->targetDir . DIRECTORY_SEPARATOR . 'provinces.php'
        );
        $regencies = require $this->targetDir . DIRECTORY_SEPARATOR . 'regencies.php';
        $this->assertSame('32.73', $regencies[0]['code']);
        $this->assertFileDoesNotExist($this->targetDir . DIRECTORY_SEPARATOR . 'districts.php');
    }

    public function test_writes_villages_in_the_line_format(): void
    {
        $this->writeJson('provinces', [['id' => '73', 'name' => 'Sulawesi Selatan']]);
        $this->writeJson('villages', [
            ['code' => '73.71.01.1002', 'name' => 'Bunga Ejaya', 'postal_code' => '90211'],
            ['code' => '73.71.01.1001', 'name' => "Ma'rang"],
        ]);

        $this->artisan('nusantara:update-data', ['--source' => $this->sourceDir])->assertSuccessful();

        $data = require $this->targetDir . DIRECTORY_SEPARATOR . 'villages.php';
        $this->assertSame("\n73.71.01.1001\tMA'RANG\t\n73.71.01.1002\tBUNGA EJAYA\t90211\n", $data);
        $this->assertSame(
            ['code' => '73.71.01.1002', 'district_code' => '73.71.01', 'name' => 'BUNGA EJAYA', 'postal_code' => '90211'],
            VillageIndex::fromData($data)->find('73.71.01.1002')
        );
    }

    public function test_warns_when_codes_collide_after_normalization(): void
    {
        $this->writeJson('provinces', [['id' => '32', 'name' => 'Jawa Barat']]);
        $this->writeJson('districts', [
            ['id' => '3273100', 'name' => 'Rancasari'],
            ['id' => '3273101', 'name' => 'Gedebage'],
        ]);

        $this->artisan('nusantara:update-data', ['--source' => $this->sourceDir])
            ->expectsOutputToContain('districts: 1 rows share a code')
            ->assertSuccessful();
    }

    public function test_fails_when_provinces_json_is_missing(): void
    {
        $this->artisan('nusantara:update-data', ['--source' => $this->sourceDir])->assertFailed();
    }

    public function test_flushes_cache_so_updated_data_is_served_immediately(): void
    {
        $this->app['config']->set('nusantara.cache_ttl', 60);

        $this->writeJson('provinces', [['id' => '32', 'name' => 'Jawa Barat']]);
        $this->artisan('nusantara:update-data', ['--source' => $this->sourceDir])->assertSuccessful();
        $this->assertSame(['JAWA BARAT'], (new Nusantara())->provinces()->names());

        $this->writeJson('provinces', [['id' => '32', 'name' => 'Jawa Barat'], ['id' => '33', 'name' => 'Jawa Tengah']]);
        $this->artisan('nusantara:update-data', ['--source' => $this->sourceDir])->assertSuccessful();
        $this->assertSame(['JAWA BARAT', 'JAWA TENGAH'], (new Nusantara())->provinces()->names());
    }
}
