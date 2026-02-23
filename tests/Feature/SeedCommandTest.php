<?php

namespace Nusantara\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nusantara\Tests\TestCase;

class SeedCommandTest extends TestCase
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
            $this->markTestSkipped('PDO SQLite extension is required for seed command tests.');
        }
        parent::setUp();
        $this->artisan('migrate', ['--path' => dirname(__DIR__, 2) . '/database/migrations']);
    }

    public function test_seed_command_fails_when_driver_is_not_database(): void
    {
        $this->app['config']->set('nusantara.driver', 'file');

        $this->artisan('nusantara:seed')
            ->assertFailed();
    }

    public function test_seed_command_fails_with_invalid_only_option(): void
    {
        $this->artisan('nusantara:seed', ['--only' => 'invalid'])
            ->assertFailed();
    }

    public function test_seed_command_succeeds_with_only_provinces(): void
    {
        $this->artisan('nusantara:seed', ['--only' => 'provinces'])
            ->assertSuccessful();
    }

    public function test_seed_command_succeeds_without_only(): void
    {
        $this->artisan('nusantara:seed')
            ->assertSuccessful();
    }
}
