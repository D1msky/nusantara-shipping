<?php

declare(strict_types=1);

namespace Nusantara\Database\Seeders;

use Illuminate\Database\Seeder;
use Nusantara\Models\District;
use Nusantara\Models\Province;
use Nusantara\Models\Regency;
use Nusantara\Models\Village;

class NusantaraSeeder extends Seeder
{
    private string $dataPath;

    public function __construct()
    {
        $base = config('nusantara.data_path');
        $this->dataPath = $base !== null && $base !== ''
            ? rtrim($base, DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';
    }

    public function run(?string $only = null): void
    {
        if ($only === null || $only === 'provinces') {
            $this->seedProvinces();
        }
        if ($only === null || $only === 'regencies') {
            $this->seedRegencies();
        }
        if ($only === null || $only === 'districts') {
            $this->seedDistricts();
        }
        if ($only === null || $only === 'villages') {
            $this->seedVillages();
        }
    }

    private function seedProvinces(): void
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'provinces.php';
        if (! is_file($file)) {
            return;
        }
        $data = require $file;
        if (! is_array($data)) {
            return;
        }
        foreach ($data as $row) {
            Province::query()->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ]
            );
        }
    }

    private function seedRegencies(): void
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'regencies.php';
        if (! is_file($file)) {
            return;
        }
        $data = require $file;
        if (! is_array($data)) {
            return;
        }
        foreach ($data as $row) {
            Regency::query()->updateOrInsert(
                ['code' => $row['code']],
                [
                    'province_code' => $row['province_code'],
                    'name' => $row['name'],
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ]
            );
        }
    }

    private function seedDistricts(): void
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'districts.php';
        if (! is_file($file)) {
            return;
        }
        $data = require $file;
        if (! is_array($data)) {
            return;
        }
        foreach ($data as $row) {
            District::query()->updateOrInsert(
                ['code' => $row['code']],
                [
                    'regency_code' => $row['regency_code'],
                    'name' => $row['name'],
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ]
            );
        }
    }

    private function seedVillages(): void
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . 'villages.php';
        if (! is_file($file)) {
            return;
        }
        $data = require $file;
        if (! is_array($data)) {
            return;
        }
        $chunks = array_chunk($data, 500);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $row) {
                Village::query()->updateOrInsert(
                    ['code' => $row['code']],
                    [
                        'district_code' => $row['district_code'],
                        'name' => $row['name'],
                        'postal_code' => $row['postal_code'] ?? null,
                    ]
                );
            }
        }
    }
}
