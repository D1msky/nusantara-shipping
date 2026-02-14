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

        $this->info('Counting regions...');

        if ($driver === 'database') {
            return $this->statsFromDatabase($cacheStatus, $driver);
        }

        return $this->statsFromFiles($nusantara, $cacheStatus, $driver);
    }

    private function statsFromDatabase(string $cacheStatus, string $driver): int
    {
        $provinceCount = \Nusantara\Models\Province::count();
        $regencyCount = \Nusantara\Models\Regency::count();
        $districtCount = \Nusantara\Models\District::count();
        $villageCount = \Nusantara\Models\Village::count();
        $postalCount = \Nusantara\Models\Village::whereNotNull('postal_code')
            ->where('postal_code', '!=', '')
            ->distinct('postal_code')
            ->count('postal_code');

        $this->printStats($provinceCount, $regencyCount, $districtCount, $villageCount, $postalCount, $driver, $cacheStatus);

        return self::SUCCESS;
    }

    private function statsFromFiles(Nusantara $nusantara, string $cacheStatus, string $driver): int
    {
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
                            $postalCodes[(string) $pc] = true;
                        }
                    }
                }
            }
        }

        $this->printStats($provinceCount, $regencyCount, $districtCount, $villageCount, count($postalCodes), $driver, $cacheStatus);

        return self::SUCCESS;
    }

    private function printStats(int $provinces, int $regencies, int $districts, int $villages, int $postalCodes, string $driver, string $cacheStatus): void
    {
        $this->newLine();
        $this->line('Nusantara Data Statistics');
        $this->line(str_repeat('-', 30));
        $this->line('Provinces:    ' . number_format($provinces));
        $this->line('Regencies:    ' . number_format($regencies));
        $this->line('Districts:    ' . number_format($districts));
        $this->line('Villages:     ' . number_format($villages));
        $this->line('Postal Codes: ' . number_format($postalCodes) . ' (unique)');
        $this->line('Driver:       ' . $driver);
        $this->line('Cache:        ' . $cacheStatus);
        $this->newLine();
    }
}
