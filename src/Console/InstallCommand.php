<?php

declare(strict_types=1);

namespace Nusantara\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'nusantara:install
                            {--migrate : Run migrations for database driver}
                            {--seed : Seed database after migration}';

    protected $description = 'Install Nusantara: publish config and optionally run migration and seed';

    public function handle(): int
    {
        $this->info('Publishing config...');
        $this->call('vendor:publish', ['--tag' => 'nusantara-config']);

        if ($this->option('migrate')) {
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        if ($this->option('seed') || ($this->option('migrate') && $this->laravel['config']->get('nusantara.driver') === 'database')) {
            $this->info('Seeding region data...');
            $this->call('nusantara:seed');
        }

        $this->info('Nusantara installed. Set NUSANTARA_DRIVER=file or database in .env and configure config/nusantara.php as needed.');
        return self::SUCCESS;
    }
}
