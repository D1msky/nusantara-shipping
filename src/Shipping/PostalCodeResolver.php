<?php

declare(strict_types=1);

namespace Nusantara\Shipping;

use Nusantara\Data\RepositoryInterface;
use Nusantara\Support\RegionCollection;

final class PostalCodeResolver
{
    public function findByPostalCode(RepositoryInterface $repository, string $postalCode): RegionCollection
    {
        return $repository->findByPostalCode($postalCode);
    }

    public function validPostalCode(RepositoryInterface $repository, string $postalCode): bool
    {
        $postalCode = preg_replace('/\D/', '', $postalCode);
        if (strlen($postalCode) !== 5) {
            return false;
        }
        return $repository->findByPostalCode($postalCode)->isNotEmpty();
    }

    /**
     * Get all unique postal codes for a regency (or district if code has 3 parts).
     *
     * @return array<int, string>
     */
    public function postalCodesForRegion(RepositoryInterface $repository, string $regionCode): array
    {
        $code = trim(str_replace(' ', '', $regionCode));
        $parts = explode('.', $code);
        $codes = [];

        if (count($parts) === 2) {
            foreach ($repository->districts($code)->all() as $d) {
                foreach ($repository->villages($d['code'])->all() as $v) {
                    $pc = $v['postal_code'] ?? null;
                    if ($pc !== null && $pc !== '') {
                        $codes[$pc] = true;
                    }
                }
            }
        } elseif (count($parts) === 3) {
            foreach ($repository->villages($code)->all() as $v) {
                $pc = $v['postal_code'] ?? null;
                if ($pc !== null && $pc !== '') {
                    $codes[$pc] = true;
                }
            }
        }

        $out = array_keys($codes);
        sort($out);
        return $out;
    }
}
