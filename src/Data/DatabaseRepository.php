<?php

declare(strict_types=1);

namespace Nusantara\Data;

use Illuminate\Database\Eloquent\Builder;
use Nusantara\Models\District;
use Nusantara\Models\Province;
use Nusantara\Models\Regency;
use Nusantara\Models\Village;
use Nusantara\Support\Cache;
use Nusantara\Support\NormalizesCode;
use Nusantara\Support\RegionCollection;

final class DatabaseRepository implements RepositoryInterface
{
    use NormalizesCode;

    private Cache $cache;

    public function __construct()
    {
        $this->cache = new Cache();
    }

    public function provinces(): RegionCollection
    {
        $data = $this->cache->get('provinces', function () {
            return Province::orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });

        return new RegionCollection($data);
    }

    public function regencies(string $provinceCode): RegionCollection
    {
        $code = $this->normalizeCode($provinceCode, 1);
        $data = $this->cache->get('regencies:' . $code, function () use ($code) {
            return Regency::where('province_code', $code)->orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });

        return new RegionCollection($data);
    }

    public function districts(string $regencyCode): RegionCollection
    {
        $code = $this->normalizeCode($regencyCode, 2);
        $data = $this->cache->get('districts:' . $code, function () use ($code) {
            return District::where('regency_code', $code)->orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });

        return new RegionCollection($data);
    }

    public function villages(string $districtCode): RegionCollection
    {
        $code = $this->normalizeCode($districtCode, 3);
        $data = $this->cache->get('villages:' . $code, function () use ($code) {
            return Village::where('district_code', $code)->orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });

        return new RegionCollection($data);
    }

    public function allRegencies(): RegionCollection
    {
        $data = $this->cache->get('regencies:*', function () {
            return Regency::orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });

        return new RegionCollection($data);
    }

    public function allDistricts(): RegionCollection
    {
        $data = $this->cache->get('districts:*', function () {
            return District::orderBy('code')->get()->map(fn ($m) => $m->toArray())->all();
        });

        return new RegionCollection($data);
    }

    public function findProvince(string $code): ?array
    {
        $code = $this->normalizeCode($code, 1);

        return $this->cache->get('province:' . $code, fn () => Province::find($code)?->toArray());
    }

    public function findRegency(string $code): ?array
    {
        $code = $this->normalizeCode($code, 2);

        return $this->cache->get('regency:' . $code, fn () => Regency::find($code)?->toArray());
    }

    public function findDistrict(string $code): ?array
    {
        $code = $this->normalizeCode($code, 3);

        return $this->cache->get('district:' . $code, fn () => District::find($code)?->toArray());
    }

    public function findVillage(string $code): ?array
    {
        $code = $this->normalizeCode($code, 4);

        return $this->cache->get('village:' . $code, fn () => Village::find($code)?->toArray());
    }

    public function search(string $term, ?string $level = null): RegionCollection
    {
        $term = trim($term);
        if ($term === '') {
            return new RegionCollection([]);
        }

        $level = $level ? strtolower($level) : null;
        $like = '%' . addcslashes(mb_strtoupper($term), '%_\\') . '%';
        $maxResults = (int) (config('nusantara.search.max_results') ?? 20);
        $results = [];

        if ($level === null || $level === 'province') {
            foreach ($this->whereNameLike(Province::query(), $like)->limit($maxResults)->get() as $m) {
                $results[] = array_merge($m->toArray(), ['level' => 'province']);
            }
        }

        if ($level === null || $level === 'regency') {
            $remaining = $maxResults - count($results);
            if ($remaining > 0) {
                foreach ($this->whereNameLike(Regency::query(), $like)->limit($remaining)->get() as $m) {
                    $results[] = array_merge($m->toArray(), ['level' => 'regency']);
                }
            }
        }

        if ($level === null || $level === 'district') {
            $remaining = $maxResults - count($results);
            if ($remaining > 0) {
                foreach ($this->whereNameLike(District::query(), $like)->limit($remaining)->get() as $m) {
                    $results[] = array_merge($m->toArray(), ['level' => 'district']);
                }
            }
        }

        if ($level === null || $level === 'village') {
            $remaining = $maxResults - count($results);
            if ($remaining > 0) {
                foreach ($this->whereNameLike(Village::query(), $like)->limit($remaining)->get() as $m) {
                    $results[] = array_merge($m->toArray(), ['level' => 'village']);
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
            $found = [];
            $villages = Village::where('postal_code', $postalCode)->with(['district.regency.province'])->get();

            foreach ($villages as $v) {
                $d = $v->district;
                $r = $d?->regency;
                $p = $r?->province;

                $found[] = array_merge($v->withoutRelations()->toArray(), [
                    'level' => 'village',
                    'province' => $p?->withoutRelations()->toArray(),
                    'regency' => $r?->withoutRelations()->toArray(),
                    'district' => $d?->withoutRelations()->toArray(),
                ]);
            }

            return $found;
        });

        return new RegionCollection($data);
    }

    /**
     * Case-insensitive on every driver (PostgreSQL LIKE is case-sensitive; names are stored upper-case).
     * $like must already be upper-cased.
     */
    private function whereNameLike(Builder $query, string $like): Builder
    {
        return $query->whereRaw('UPPER(name) LIKE ?', [$like]);
    }
}
