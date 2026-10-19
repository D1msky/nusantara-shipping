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
        // Scoped: one instance per request/job, so in-process memoization never outlives a
        // cache flush in long-running workers (Octane, queue).
        $this->app->scoped(Nusantara::class, fn () => new Nusantara());
    }

    public static function migrationsPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/nusantara.php' => config_path('nusantara.php'),
            ], 'nusantara-config');
            // Only the database driver uses the tables; file-driver apps would otherwise get four
            // empty tables on every `php artisan migrate`.
            if (config('nusantara.driver') === 'database') {
                $this->loadMigrationsFrom(self::migrationsPath());
            }
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
