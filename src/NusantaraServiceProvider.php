<?php

declare(strict_types=1);

namespace Nusantara;

use Illuminate\Support\ServiceProvider;
use Nusantara\Console\ClearCacheCommand;
use Nusantara\Console\InstallCommand;
use Nusantara\Console\SeedCommand;
use Nusantara\Console\StatsCommand;
use Nusantara\Console\UpdateDataCommand;

class NusantaraServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nusantara.php',
            'nusantara'
        );
        $this->app->singleton(Nusantara::class, fn () => new Nusantara());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/nusantara.php' => config_path('nusantara.php'),
            ], 'nusantara-config');
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            $this->commands([
                InstallCommand::class,
                SeedCommand::class,
                UpdateDataCommand::class,
                StatsCommand::class,
                ClearCacheCommand::class,
            ]);
        }
    }
}
