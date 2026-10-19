<?php

namespace Nusantara\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Nusantara\Models\Province;
use Nusantara\NusantaraServiceProvider;
use Nusantara\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_install_command_publishes_config(): void
    {
        $configPath = config_path('nusantara.php');

        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->artisan('nusantara:install')
            ->assertSuccessful();

        $this->assertFileExists($configPath);
    }

    public function test_install_command_with_migrate_option_calls_migrate(): void
    {
        $this->app['config']->set('nusantara.driver', 'database');
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is required.');
        }

        $this->artisan('nusantara:install', ['--migrate' => true])
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('nusantara_villages'));
        $this->assertSame(1, Province::where('code', '32')->count(), 'migrate with the database driver also seeds');
    }

    public function test_install_command_with_migrate_option_fails_for_file_driver(): void
    {
        $this->artisan('nusantara:install', ['--migrate' => true])
            ->expectsOutputToContain('--migrate needs NUSANTARA_DRIVER=database')
            ->assertFailed();
    }

    public function test_migrations_are_not_registered_for_file_driver(): void
    {
        $this->assertNotContains(
            realpath(NusantaraServiceProvider::migrationsPath()),
            array_map('realpath', $this->app['migrator']->paths())
        );
    }
}
