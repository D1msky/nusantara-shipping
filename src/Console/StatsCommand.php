<?php

declare(strict_types=1);

namespace Nusantara\Console;

use Illuminate\Console\Command;
use Nusantara\Nusantara;

class StatsCommand extends Command
{
    protected $signature = 'nusantara:stats';

    protected $description = 'Display Nusantara data statistics';

    public function handle(Nusantara $nusantara): int
    {
        $driver = config('nusantara.driver', 'file');
        $cacheTtl = (int) config('nusantara.cache_ttl', 86400);
        $cacheStatus = $cacheTtl > 0 ? "enabled ({$cacheTtl}s)" : 'disabled';

        $provinces = $nusantara->provinces();
        $provinceCount = $provinces->count();

        $regencyCount = 0;
        $districtCount = 0;
        $villageCount = 0;
        $postalCodes = [];

        foreach ($provinces->all() as $p) {
            $regencies = $nusantara->regencies($p['code']);
            $regencyCount += $regencies->count();
            foreach ($regencies->all() as $r) {
                $districts = $nusantara->districts($r['code']);
                $districtCount += $districts->count();
                foreach ($districts->all() as $d) {
                    $villages = $nusantara->villages($d['code']);
                    $villageCount += $villages->count();
                    foreach ($villages->all() as $v) {
                        $pc = $v['postal_code'] ?? null;
                        if ($pc !== null && $pc !== '') {
                            $postalCodes[$pc] = true;
                        }
                    }
                }
            }
        }

        $postalCount = count($postalCodes);

        $this->line('');
        $this->line('🏝️  Nusantara Data Statistics');
        $this->line('─────────────────────────────');
        $this->line("Provinces:    " . number_format($provinceCount));
        $this->line("Regencies:    " . number_format($regencyCount));
        $this->line("Districts:    " . number_format($districtCount));
        $this->line("Villages:     " . number_format($villageCount));
        $this->line("Postal Codes: " . number_format($postalCount) . ' (unique)');
        $this->line("Driver:       {$driver}");
        $this->line("Cache:        {$cacheStatus}");
        $this->line('');

        return self::SUCCESS;
    }
}
