<?php

declare(strict_types=1);

namespace Nusantara;

use Nusantara\Data\DatabaseRepository;
use Nusantara\Data\FileRepository;
use Nusantara\Data\RepositoryInterface;
use Nusantara\Enums\RegionLevel;
use Nusantara\Search\FuzzyMatcher;
use Nusantara\Shipping\AddressFormatter;
use Nusantara\Shipping\PostalCodeResolver;
use Nusantara\Support\RegionCollection;

final class Nusantara
{
    private ?RepositoryInterface $repository = null;

    private ?FuzzyMatcher $fuzzyMatcher = null;

    private ?AddressFormatter $addressFormatter = null;

    private ?PostalCodeResolver $postalCodeResolver = null;

    public function repository(): RepositoryInterface
    {
        if ($this->repository === null) {
            $driver = config('nusantara.driver', 'file');
            $this->repository = $driver === 'database'
                ? new DatabaseRepository()
                : new FileRepository();
        }
        return $this->repository;
    }

    public function provinces(): RegionCollection
    {
        return $this->repository()->provinces();
    }

    public function regencies(string $provinceCodeOrName): RegionCollection
    {
        $repo = $this->repository();
        $code = $this->resolveProvinceCode($repo, $provinceCodeOrName);
        return $code !== null ? $repo->regencies($code) : new RegionCollection([]);
    }

    public function districts(string $regencyCode): RegionCollection
    {
        return $this->repository()->districts($regencyCode);
    }

    public function villages(string $districtCode): RegionCollection
    {
        return $this->repository()->villages($districtCode);
    }

    public function find(string $code): ?array
    {
        $code = trim(str_replace(' ', '', $code));
        $level = RegionLevel::fromCode($code);
        $repo = $this->repository();
        return match ($level) {
            RegionLevel::Province => $repo->findProvince($code),
            RegionLevel::Regency => $repo->findRegency($code),
            RegionLevel::District => $repo->findDistrict($code),
            RegionLevel::Village => $repo->findVillage($code),
        };
    }

    public function search(string $term, ?string $level = null): RegionCollection
    {
        $matcher = $this->fuzzyMatcher ??= new FuzzyMatcher();
        return $matcher->search($this->repository(), $term, $level);
    }

    public function postalCode(string $postalCode): RegionCollection
    {
        $resolver = $this->postalCodeResolver ??= new PostalCodeResolver();
        return $resolver->findByPostalCode($this->repository(), $postalCode);
    }

    public function validPostalCode(string $postalCode): bool
    {
        $resolver = $this->postalCodeResolver ??= new PostalCodeResolver();
        return $resolver->validPostalCode($this->repository(), $postalCode);
    }

    /**
     * Get all postal codes for a regency or district code (e.g. "32.73" or "32.73.01").
     *
     * @return array<int, string>
     */
    public function postalCodes(string $regionCode): array
    {
        $resolver = $this->postalCodeResolver ??= new PostalCodeResolver();
        return $resolver->postalCodesForRegion($this->repository(), $regionCode);
    }

    /**
     * @return array{province?: array, regency?: array, district?: array, village?: array}|null
     */
    public function hierarchy(string $code): ?array
    {
        $code = trim(str_replace(' ', '', $code));
        $parts = explode('.', $code);
        $repo = $this->repository();
        $out = [];
        if (count($parts) >= 1 && $parts[0] !== '') {
            $p = $repo->findProvince($parts[0]);
            if ($p) {
                $out['province'] = $p;
            }
        }
        if (count($parts) >= 2 && isset($out['province'])) {
            $r = $repo->findRegency($parts[0] . '.' . $parts[1]);
            if ($r) {
                $out['regency'] = $r;
            }
        }
        if (count($parts) >= 3 && isset($out['regency'])) {
            $d = $repo->findDistrict($parts[0] . '.' . $parts[1] . '.' . $parts[2]);
            if ($d) {
                $out['district'] = $d;
            }
        }
        if (count($parts) >= 4 && isset($out['district'])) {
            $v = $repo->findVillage($code);
            if ($v) {
                $out['village'] = $v;
            }
        }
        return $out ?: null;
    }

    public function shippingAddress(
        string $regionCode,
        ?string $format = null,
        ?string $style = null
    ): ?string {
        $formatter = $this->addressFormatter ??= new AddressFormatter();
        return $formatter->format($this->repository(), $regionCode, $format, $style);
    }

    /**
     * Get coordinates for a region (province, regency, or district). Prefer deepest level.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    public function coordinates(string $code): array
    {
        $code = trim(str_replace(' ', '', $code));
        $repo = $this->repository();
        $parts = explode('.', $code);
        if (count($parts) >= 3) {
            $d = $repo->findDistrict($code);
            if ($d && (isset($d['latitude']) || isset($d['longitude']))) {
                return ['latitude' => $d['latitude'] ?? null, 'longitude' => $d['longitude'] ?? null];
            }
        }
        if (count($parts) >= 2) {
            $r = $repo->findRegency($code);
            if ($r) {
                return ['latitude' => $r['latitude'] ?? null, 'longitude' => $r['longitude'] ?? null];
            }
        }
        if (count($parts) >= 1) {
            $p = $repo->findProvince($parts[0]);
            if ($p) {
                return ['latitude' => $p['latitude'] ?? null, 'longitude' => $p['longitude'] ?? null];
            }
        }
        return ['latitude' => null, 'longitude' => null];
    }

    /**
     * Find nearest regency from coordinates (Haversine approximation).
     *
     * @return array{code: string, name: string, distance_km: float}|null
     */
    public function nearestRegency(float $latitude, float $longitude): ?array
    {
        $repo = $this->repository();
        $best = null;
        $bestKm = PHP_FLOAT_MAX;
        foreach ($repo->provinces()->all() as $p) {
            foreach ($repo->regencies($p['code'])->all() as $r) {
                $lat = $r['latitude'] ?? $p['latitude'] ?? null;
                $lon = $r['longitude'] ?? $p['longitude'] ?? null;
                if ($lat === null || $lon === null) {
                    continue;
                }
                $km = $this->haversineKm((float) $lat, (float) $lon, $latitude, $longitude);
                if ($km < $bestKm) {
                    $bestKm = $km;
                    $best = ['code' => $r['code'], 'name' => $r['name'], 'distance_km' => round($km, 2)];
                }
            }
        }
        return $best;
    }

    private function resolveProvinceCode(RepositoryInterface $repo, string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        if (str_contains($input, '.')) {
            return explode('.', $input)[0] ?? null;
        }
        if (strlen($input) === 2 && ctype_digit($input)) {
            return $input;
        }
        foreach ($repo->provinces()->all() as $p) {
            if (mb_strtoupper($p['name'] ?? '') === mb_strtoupper($input)) {
                return $p['code'] ?? null;
            }
        }
        $search = $this->search($input, 'province');
        $first = $search->first();
        return $first['code'] ?? null;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }
}
