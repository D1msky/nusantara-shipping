<?php

declare(strict_types=1);

namespace Nusantara\Data;

use Nusantara\Support\RegionCollection;

interface RepositoryInterface
{
    public function provinces(): RegionCollection;

    public function regencies(string $provinceCode): RegionCollection;

    public function districts(string $regencyCode): RegionCollection;

    public function villages(string $districtCode): RegionCollection;

    /** All regencies across every province, for full scans (fuzzy search, nearest lookup). */
    public function allRegencies(): RegionCollection;

    /** All districts across every regency, for full scans (fuzzy search). */
    public function allDistricts(): RegionCollection;

    public function findProvince(string $code): ?array;

    public function findRegency(string $code): ?array;

    public function findDistrict(string $code): ?array;

    public function findVillage(string $code): ?array;

    public function search(string $term, ?string $level = null): RegionCollection;

    public function findByPostalCode(string $postalCode): RegionCollection;
}
