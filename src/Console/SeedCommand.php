<?php

declare(strict_types=1);

namespace Nusantara\Console;

use Illuminate\Console\Command;
use Nusantara\Database\Seeders\NusantaraSeeder;

class SeedCommand extends Command
{
    protected $signature = 'nusantara:seed
                            {--only= : Only seed one level: provinces|regencies|districts|villages}';

    protected $description = 'Seed Nusantara region data from package data files into the database';

    public function handle(): int
    {
        if (config('nusantara.driver') !== 'database') {
            $this->warn('Database driver not set. Set NUSANTARA_DRIVER=database and run migrations first.');
            return self::FAILURE;
        }

        $only = $this->option('only');
        if ($only !== null && ! in_array($only, ['provinces', 'regencies', 'districts', 'villages'], true)) {
            $this->error('--only must be one of: provinces, regencies, districts, villages');
            return self::FAILURE;
        }

        $seeder = new NusantaraSeeder();
        $seeder->run($only);
        $this->info('Nusantara data seeded successfully.');
        return self::SUCCESS;
    }
}
