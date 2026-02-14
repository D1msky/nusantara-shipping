<?php

declare(strict_types=1);

namespace Nusantara\Data;

use Nusantara\Models\District;
use Nusantara\Models\Province;
use Nusantara\Models\Regency;
use Nusantara\Models\Village;
use Nusantara\Support\Cache;
use Nusantara\Support\RegionCollection;

final class DatabaseRepository implements RepositoryInterface
{
    public function provinces(): RegionCollection
    {
        $cache = new Cache();
        $data = $cache->get('provinces', function () {
            return Province::orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });
        return new RegionCollection($data);
    }

    public function regencies(string $provinceCode): RegionCollection
    {
        $code = $this->normalizeCode($provinceCode, 1);
        $cache = new Cache();
        $data = $cache->get('regencies:' . $code, function () use ($code) {
            return Regency::where('province_code', $code)->orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });
        return new RegionCollection($data);
    }

    public function districts(string $regencyCode): RegionCollection
    {
        $code = $this->normalizeCode($regencyCode, 2);
        $cache = new Cache();
        $data = $cache->get('districts:' . $code, function () use ($code) {
            return District::where('regency_code', $code)->orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });
        return new RegionCollection($data);
    }

    public function villages(string $districtCode): RegionCollection
    {
        $code = $this->normalizeCode($districtCode, 3);
        $cache = new Cache();
        $data = $cache->get('villages:' . $code, function () use ($code) {
            return Village::where('district_code', $code)->orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });
        return new RegionCollection($data);
    }

    public function findProvince(string $code): ?array
    {
        $code = $this->normalizeCode($code, 1);
        $m = Province::find($code);
        return $m ? $m->toArray() : null;
    }

    public function findRegency(string $code): ?array
    {
        $code = $this->normalizeCode($code, 2);
        $m = Regency::find($code);
        return $m ? $m->toArray() : null;
    }

    public function findDistrict(string $code): ?array
    {
        $code = $this->normalizeCode($code, 3);
        $m = District::find($code);
        return $m ? $m->toArray() : null;
    }

    public function findVillage(string $code): ?array
    {
        $code = $this->normalizeCode($code, 4);
        $m = Village::find($code);
        return $m ? $m->toArray() : null;
    }

    public function search(string $term, ?string $level = null): RegionCollection
    {
        $term = trim($term);
        if ($term === '') {
            return new RegionCollection([]);
        }
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $results = [];

        if ($level === null || $level === 'province') {
            foreach (Province::where('name', 'like', $like)->get() as $m) {
                $results[] = array_merge($m->toArray(), ['level' => 'province']);
            }
        }
        if ($level === null || $level === 'regency') {
            foreach (Regency::where('name', 'like', $like)->get() as $m) {
                $results[] = array_merge($m->toArray(), ['level' => 'regency']);
            }
        }
        if ($level === null || $level === 'district') {
            foreach (District::where('name', 'like', $like)->get() as $m) {
                $results[] = array_merge($m->toArray(), ['level' => 'district']);
            }
        }
        if ($level === null || $level === 'village') {
            foreach (Village::where('name', 'like', $like)->get() as $m) {
                $results[] = array_merge($m->toArray(), ['level' => 'village']);
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
            $villages = Village::where('postal_code', $postalCode)->with(['district.regency.province'])->get();
            foreach ($villages as $v) {
                $d = $v->district;
                $r = $d ? $d->regency : null;
                $p = $r ? $r->province : null;
                $found[] = array_merge($v->toArray(), [
                    'level' => 'village',
                    'province' => $p ? $p->toArray() : null,
                    'regency' => $r ? $r->toArray() : null,
                    'district' => $d ? $d->toArray() : null,
                ]);
            }
            return $found;
        });
        return new RegionCollection($data);
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
}
