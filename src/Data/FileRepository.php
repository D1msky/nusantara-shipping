<?php

declare(strict_types=1);

namespace Nusantara\Data;

use Nusantara\Support\Cache;
use Nusantara\Support\NormalizesCode;
use Nusantara\Support\RegionCollection;

final class FileRepository implements RepositoryInterface
{
    use NormalizesCode;

    private string $dataPath;

    private Cache $cache;

    /** @var array<int, array>|null in-memory provinces (small, always loaded) */
    private ?array $provincesData = null;

    /** @var array<string, array<int, array>>|null in-memory regencies keyed by province_code */
    private ?array $regenciesIndex = null;

    /** @var array<string, array<int, array>>|null in-memory districts keyed by regency_code */
    private ?array $districtsIndex = null;

    /** @var array<string, array<int, array>>|null in-memory villages keyed by district_code */
    private ?array $villagesIndex = null;

    /** @var array<string, array<int, array>>|null in-memory villages keyed by postal_code */
    private ?array $postalIndex = null;

    public function __construct()
    {
        $base = config('nusantara.data_path');
        $this->dataPath = $base !== null && $base !== ''
            ? rtrim($base, DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';
        $this->cache = new Cache();
    }

    public function provinces(): RegionCollection
    {
        $data = $this->cache->get('provinces', fn () => $this->loadProvinces());

        return new RegionCollection($data);
    }

    public function regencies(string $provinceCode): RegionCollection
    {
        $code = $this->normalizeCode($provinceCode, 1);
        $data = $this->cache->get('regencies:' . $code, fn () => $this->loadRegenciesFor($code));

        return new RegionCollection($data);
    }

    public function districts(string $regencyCode): RegionCollection
    {
        $code = $this->normalizeCode($regencyCode, 2);
        $data = $this->cache->get('districts:' . $code, fn () => $this->loadDistrictsFor($code));

        return new RegionCollection($data);
    }

    public function villages(string $districtCode): RegionCollection
    {
        $code = $this->normalizeCode($districtCode, 3);
        $data = $this->cache->get('villages:' . $code, fn () => $this->loadVillagesFor($code));

        return new RegionCollection($data);
    }

    public function findProvince(string $code): ?array
    {
        $code = $this->normalizeCode($code, 1);
        foreach ($this->provinces()->all() as $item) {
            if (($item['code'] ?? '') === $code) {
                return $item;
            }
        }

        return null;
    }

    public function findRegency(string $code): ?array
    {
        $code = $this->normalizeCode($code, 2);
        $provinceCode = explode('.', $code)[0] ?? '';
        foreach ($this->regencies($provinceCode)->all() as $item) {
            if (($item['code'] ?? '') === $code) {
                return $item;
            }
        }

        return null;
    }

    public function findDistrict(string $code): ?array
    {
        $code = $this->normalizeCode($code, 3);
        $regencyCode = implode('.', array_slice(explode('.', $code), 0, 2));
        foreach ($this->districts($regencyCode)->all() as $item) {
            if (($item['code'] ?? '') === $code) {
                return $item;
            }
        }

        return null;
    }

    public function findVillage(string $code): ?array
    {
        $code = $this->normalizeCode($code, 4);
        $districtCode = implode('.', array_slice(explode('.', $code), 0, 3));
        foreach ($this->villages($districtCode)->all() as $item) {
            if (($item['code'] ?? '') === $code) {
                return $item;
            }
        }

        return null;
    }

    public function search(string $term, ?string $level = null): RegionCollection
    {
        $term = trim($term);
        if ($term === '') {
            return new RegionCollection([]);
        }

        $results = [];
        $level = $level ? strtolower($level) : null;
        $maxResults = (int) (config('nusantara.search.max_results') ?? 20);

        if ($level === null || $level === 'province') {
            foreach ($this->provinces()->all() as $p) {
                if ($this->nameMatches($p['name'] ?? '', $term)) {
                    $results[] = array_merge($p, ['level' => 'province']);
                }
            }
        }

        if ($level === null || $level === 'regency') {
            $this->ensureRegenciesIndex();
            foreach ($this->regenciesIndex as $items) {
                foreach ($items as $r) {
                    if ($this->nameMatches($r['name'] ?? '', $term)) {
                        $results[] = array_merge($r, ['level' => 'regency']);
                        if (count($results) >= $maxResults) {
                            return new RegionCollection($results);
                        }
                    }
                }
            }
        }

        if ($level === null || $level === 'district') {
            $this->ensureDistrictsIndex();
            foreach ($this->districtsIndex as $items) {
                foreach ($items as $d) {
                    if ($this->nameMatches($d['name'] ?? '', $term)) {
                        $results[] = array_merge($d, ['level' => 'district']);
                        if (count($results) >= $maxResults) {
                            return new RegionCollection($results);
                        }
                    }
                }
            }
        }

        if ($level === null || $level === 'village') {
            $this->ensureVillagesIndex();
            foreach ($this->villagesIndex as $items) {
                foreach ($items as $v) {
                    if ($this->nameMatches($v['name'] ?? '', $term)) {
                        $results[] = array_merge($v, ['level' => 'village']);
                        if (count($results) >= $maxResults) {
                            return new RegionCollection($results);
                        }
                    }
                }
            }
        }

        return new RegionCollection($results);
    }

    public function findByPostalCode(string $postalCode): RegionCollection
    {
        $postalCode = preg_replace('/\D/', '', $postalCode);
        if (strlen($postalCode) !== 5) {
            return new RegionCollection([]);
        }

        $data = $this->cache->get('postal:' . $postalCode, function () use ($postalCode) {
            $this->ensurePostalIndex();
            $villages = $this->postalIndex[$postalCode] ?? [];

            if (empty($villages)) {
                return [];
            }

            $found = [];
            foreach ($villages as $v) {
                $parts = explode('.', $v['district_code'] ?? '');
                $regencyCode = implode('.', array_slice($parts, 0, 2));
                $provinceCode = $parts[0] ?? '';

                $p = $this->findProvince($provinceCode);
                $r = $this->findRegency($regencyCode);
                $d = $this->findDistrict($v['district_code'] ?? '');

                $found[] = array_merge($v, [
                    'level' => 'village',
                    'province' => $p,
                    'regency' => $r,
                    'district' => $d,
                ]);
            }

            return $found;
        });

        return new RegionCollection($data);
    }

    // ---- Lazy data loading with indexed lookups ----

    private function loadProvinces(): array
    {
        if ($this->provincesData !== null) {
            return $this->provincesData;
        }

        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'provinces.php';
        if (! is_file($file)) {
            return $this->provincesData = [];
        }

        $data = require $file;
        $this->provincesData = is_array($data) ? $data : [];

        return $this->provincesData;
    }

    private function loadRegenciesFor(string $provinceCode): array
    {
        $this->ensureRegenciesIndex();

        return $this->regenciesIndex[$provinceCode] ?? [];
    }

    private function loadDistrictsFor(string $regencyCode): array
    {
        $this->ensureDistrictsIndex();

        return $this->districtsIndex[$regencyCode] ?? [];
    }

    private function loadVillagesFor(string $districtCode): array
    {
        $this->ensureVillagesIndex();

        return $this->villagesIndex[$districtCode] ?? [];
    }

    private function ensureRegenciesIndex(): void
    {
        if ($this->regenciesIndex !== null) {
            return;
        }

        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'regencies.php';
        $this->regenciesIndex = [];

        if (! is_file($file)) {
            return;
        }

        $all = require $file;
        if (! is_array($all)) {
            return;
        }

        foreach ($all as $row) {
            $pc = $row['province_code'] ?? '';
            $this->regenciesIndex[$pc][] = $row;
        }
    }

    private function ensureDistrictsIndex(): void
    {
        if ($this->districtsIndex !== null) {
            return;
        }

        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'districts.php';
        $this->districtsIndex = [];

        if (! is_file($file)) {
            return;
        }

        $all = require $file;
        if (! is_array($all)) {
            return;
        }

        foreach ($all as $row) {
            $rc = $row['regency_code'] ?? '';
            $this->districtsIndex[$rc][] = $row;
        }
    }

    private function ensureVillagesIndex(): void
    {
        if ($this->villagesIndex !== null) {
            return;
        }

        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'villages.php';
        $this->villagesIndex = [];

        if (! is_file($file)) {
            return;
        }

        $all = require $file;
        if (! is_array($all)) {
            return;
        }

        foreach ($all as $row) {
            $dc = $row['district_code'] ?? '';
            $this->villagesIndex[$dc][] = $row;
        }
    }

    private function ensurePostalIndex(): void
    {
        if ($this->postalIndex !== null) {
            return;
        }

        $this->ensureVillagesIndex();
        $this->postalIndex = [];

        foreach ($this->villagesIndex as $villages) {
            foreach ($villages as $v) {
                $pc = $v['postal_code'] ?? null;
                if ($pc !== null && $pc !== '') {
                    $this->postalIndex[(string) $pc][] = $v;
                }
            }
        }
    }

    private function nameMatches(string $name, string $term): bool
    {
        return str_contains(mb_strtolower($name), mb_strtolower($term));
    }
}
