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

    private ?VillageIndex $villageIndex = null;

    /** @var array<string, array{stamp: string|null, value: mixed}> per-process, keyed by file path */
    private static array $loaded = [];

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

    public function allRegencies(): RegionCollection
    {
        // Read straight from the data file: with OPcache this is cheaper than unserializing a large cache entry.
        $this->ensureRegenciesIndex();

        return new RegionCollection(array_merge(...array_values($this->regenciesIndex)));
    }

    public function allDistricts(): RegionCollection
    {
        $this->ensureDistrictsIndex();

        return new RegionCollection(array_merge(...array_values($this->districtsIndex)));
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
        $term = mb_strtolower($term);

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
            foreach ($this->villageIndex()->search($term, $maxResults - count($results)) as $v) {
                $results[] = array_merge($v, ['level' => 'village']);
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
            $villages = $this->villageIndex()->byPostalCode($postalCode);

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
        return $this->villageIndex()->forDistrict($districtCode);
    }

    private function ensureRegenciesIndex(): void
    {
        $this->regenciesIndex ??= $this->derived('regencies.php', fn (mixed $rows) => $this->groupBy($rows, 'province_code'));
    }

    private function ensureDistrictsIndex(): void
    {
        $this->districtsIndex ??= $this->derived('districts.php', fn (mixed $rows) => $this->groupBy($rows, 'regency_code'));
    }

    private function villageIndex(): VillageIndex
    {
        return $this->villageIndex ??= $this->derived('villages.php', fn (mixed $data) => VillageIndex::fromData($data));
    }

    /** @return array<string, array<int, array>> */
    private function groupBy(mixed $rows, string $column): array
    {
        $groups = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $groups[$row[$column] ?? ''][] = $row;
        }

        return $groups;
    }

    /**
     * Structure built once per process from a data file and shared by every instance, so
     * long-running workers (Octane, queue) that create a repository per request/job don't
     * re-read the files on each cache miss. Keyed on mtime + size so files rewritten by
     * nusantara:update-data are picked up.
     *
     * @param callable(mixed): mixed $build receives the file's return value (null when missing)
     */
    private function derived(string $file, callable $build): mixed
    {
        $path = $this->dataPath . DIRECTORY_SEPARATOR . $file;
        clearstatcache(true, $path);
        $stamp = is_file($path) ? filemtime($path) . ':' . filesize($path) : null;

        if ((self::$loaded[$path]['stamp'] ?? false) !== $stamp) {
            self::$loaded[$path] = ['stamp' => $stamp, 'value' => $build($stamp !== null ? require $path : null)];
        }

        return self::$loaded[$path]['value'];
    }

    /** @param string $term already lower-cased by the caller, so it is not re-lowered for every row */
    private function nameMatches(string $name, string $term): bool
    {
        return str_contains(mb_strtolower($name), $term);
    }
}
