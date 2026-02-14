<?php

namespace Nusantara\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nusantara\Data\DatabaseRepository;
use Nusantara\Models\District;
use Nusantara\Models\Province;
use Nusantara\Models\Regency;
use Nusantara\Models\Village;
use Nusantara\Tests\TestCase;

class DatabaseRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('nusantara.driver', 'database');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is required for database repository tests.');
        }
        parent::setUp();
        $this->artisan('migrate', ['--path' => dirname(__DIR__, 2) . '/database/migrations']);
        $this->seedDatabase();
    }

    private function seedDatabase(): void
    {
        Province::query()->insert(['code' => '32', 'name' => 'JAWA BARAT', 'latitude' => -6.91, 'longitude' => 107.61]);
        Regency::query()->insert(['code' => '32.73', 'province_code' => '32', 'name' => 'KOTA BANDUNG', 'latitude' => -6.92, 'longitude' => 107.62]);
        District::query()->insert(['code' => '32.73.01', 'regency_code' => '32.73', 'name' => 'BANDUNG WETAN', 'latitude' => null, 'longitude' => null]);
        Village::query()->insert(['code' => '32.73.01.1001', 'district_code' => '32.73.01', 'name' => 'CIHAPIT', 'postal_code' => '40114']);
    }

    public function test_database_repository_returns_provinces(): void
    {
        $repo = new DatabaseRepository();
        $provinces = $repo->provinces();
        $this->assertGreaterThan(0, $provinces->count());
        $this->assertEquals('32', $provinces->first()['code']);
    }

    public function test_database_repository_find_village(): void
    {
        $repo = new DatabaseRepository();
        $v = $repo->findVillage('32.73.01.1001');
        $this->assertNotNull($v);
        $this->assertEquals('CIHAPIT', $v['name']);
    }

    public function test_database_repository_find_by_postal_code(): void
    {
        $repo = new DatabaseRepository();
        $r = $repo->findByPostalCode('40114');
        $this->assertGreaterThan(0, $r->count());
    }
}
