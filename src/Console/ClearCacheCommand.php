<?php

declare(strict_types=1);

namespace Nusantara\Console;

use Illuminate\Console\Command;
use Nusantara\Support\Cache;

class ClearCacheCommand extends Command
{
    protected $signature = 'nusantara:clear-cache';

    protected $description = 'Clear Nusantara cache';

    public function handle(): int
    {
        $cache = new Cache();
        $cache->flush();
        $this->info('Nusantara cache cleared.');
        return self::SUCCESS;
    }
}
