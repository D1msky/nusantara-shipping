<?php

namespace Nusantara\Tests\Feature;

use Illuminate\Support\Facades\File;
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
    }
}
