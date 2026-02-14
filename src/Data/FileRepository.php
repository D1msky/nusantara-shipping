<?php

declare(strict_types=1);

namespace Nusantara\Data;

use Nusantara\Support\Cache;
use Nusantara\Support\RegionCollection;

final class FileRepository implements RepositoryInterface
{
    private string $dataPath;

    /** @var array<string, array>|null */
    private ?array $provinces = null;

    public function __construct()
    {
        $base = config('nusantara.data_path');
        $this->dataPath = $base !== null && $base !== ''
            ? rtrim($base, DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';
    }

    public function provinces(): RegionCollection
    {
        $cache = new Cache();
        $data = $cache->get('provinces', fn () => $this->loadProvinces());
        return new RegionCollection($data);
    }

    public function regencies(string $provinceCode): RegionCollection
    {
        $code = $this->normalizeCode($provinceCode, 1);
        $cache = new Cache();
        $data = $cache->get('regencies:' . $code, fn () => $this->loadRegencies($code));
        return new RegionCollection($data);
    }

    public function districts(string $regencyCode): RegionCollection
    {
        $code = $this->normalizeCode($regencyCode, 2);
        $cache = new Cache();
        $data = $cache->get('districts:' . $code, fn () => $this->loadDistricts($code));
        return new RegionCollection($data);
    }

    public function villages(string $districtCode): RegionCollection
    {
        $code = $this->normalizeCode($districtCode, 3);
        $cache = new Cache();
        $data = $cache->get('villages:' . $code, fn () => $this->loadVillages($code));
        return new RegionCollection($data);
    }

    public function findProvince(string $code): ?array
    {
        $code = $this->normalizeCode($code, 1);
        $all = $this->provinces()->all();
        foreach ($all as $item) {
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
        $all = $this->regencies($provinceCode)->all();
        foreach ($all as $item) {
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
        $all = $this->districts($regencyCode)->all();
        foreach ($all as $item) {
            if (($item['code'] ?? '') === $code) {
                return $item;
            }
        }
        return null;
    }

    public function findVillage(string $code): ?array
    {
        $code = $this->normalizeCode($code, 4);
        $parts = explode('.', $code);
        $districtCode = implode('.', array_slice($parts, 0, 3));
        $all = $this->villages($districtCode)->all();
        foreach ($all as $item) {
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

        if ($level === null || $level === 'province') {
            foreach ($this->provinces()->all() as $p) {
                if ($this->nameMatches($p['name'] ?? '', $term)) {
                    $results[] = array_merge($p, ['level' => 'province']);
                }
            }
        }
        if ($level === null || $level === 'regency') {
            foreach ($this->provinces()->all() as $p) {
                foreach ($this->regencies($p['code'])->all() as $r) {
                    if ($this->nameMatches($r['name'] ?? '', $term)) {
                        $results[] = array_merge($r, ['level' => 'regency']);
                    }
                }
            }
        }
        if ($level === null || $level === 'district') {
            foreach ($this->provinces()->all() as $p) {
                foreach ($this->regencies($p['code'])->all() as $r) {
                    foreach ($this->districts($r['code'])->all() as $d) {
                        if ($this->nameMatches($d['name'] ?? '', $term)) {
                            $results[] = array_merge($d, ['level' => 'district']);
                        }
                    }
                }
            }
        }
        if ($level === null || $level === 'village') {
            foreach ($this->provinces()->all() as $p) {
                foreach ($this->regencies($p['code'])->all() as $r) {
                    foreach ($this->districts($r['code'])->all() as $d) {
                        foreach ($this->villages($d['code'])->all() as $v) {
                            if ($this->nameMatches($v['name'] ?? '', $term)) {
                                $results[] = array_merge($v, ['level' => 'village']);
                            }
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
        $cache = new Cache();
        $data = $cache->get('postal:' . $postalCode, function () use ($postalCode) {
            $found = [];
            foreach ($this->provinces()->all() as $p) {
                foreach ($this->regencies($p['code'])->all() as $r) {
                    foreach ($this->districts($r['code'])->all() as $d) {
                        foreach ($this->villages($d['code'])->all() as $v) {
                            if (($v['postal_code'] ?? '') === $postalCode) {
                                $found[] = array_merge($v, [
                                    'level' => 'village',
                                    'province' => $p,
                                    'regency' => $r,
                                    'district' => $d,
                                ]);
                            }
                        }
                    }
                }
            }
            return $found;
        });
        return new RegionCollection($data);
    }

    private function loadProvinces(): array
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'provinces.php';
        if (! is_file($file)) {
            return [];
        }
        $data = require $file;
        return is_array($data) ? $data : [];
    }

    private function loadRegencies(string $provinceCode): array
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'regencies.php';
        if (! is_file($file)) {
            return [];
        }
        $all = require $file;
        if (! is_array($all)) {
            return [];
        }
        $code = $provinceCode;
        return array_values(array_filter($all, fn (array $r) => ($r['province_code'] ?? '') === $code));
    }

    private function loadDistricts(string $regencyCode): array
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'districts.php';
        if (! is_file($file)) {
            return [];
        }
        $all = require $file;
        if (! is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, fn (array $d) => ($d['regency_code'] ?? '') === $regencyCode));
    }

    private function loadVillages(string $districtCode): array
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'villages.php';
        if (! is_file($file)) {
            return [];
        }
        $all = require $file;
        if (! is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, fn (array $v) => ($v['district_code'] ?? '') === $districtCode));
    }

    private function normalizeCode(string $code, int $maxParts): string
    {
        $code = trim(str_replace(' ', '', $code));
        if (str_contains($code, '.')) {
            $parts = explode('.', $code);
            return implode('.', array_slice($parts, 0, $maxParts));
        }
        if (strlen($code) === 2 && $maxParts >= 1) {
            return $code;
        }
        if (strlen($code) === 4 && $maxParts >= 2) {
            return substr($code, 0, 2) . '.' . substr($code, 2, 2);
        }
        if (strlen($code) === 6 && $maxParts >= 3) {
            return substr($code, 0, 2) . '.' . substr($code, 2, 2) . '.' . substr($code, 4, 2);
        }
        if (strlen($code) === 10 && $maxParts >= 4) {
            return substr($code, 0, 2) . '.' . substr($code, 2, 2) . '.' . substr($code, 4, 2) . '.' . substr($code, 6, 4);
        }
        return $code;
    }

    private function nameMatches(string $name, string $term): bool
    {
        return str_contains(mb_strtolower($name), mb_strtolower($term));
    }
}
