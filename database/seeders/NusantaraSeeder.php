<?php

declare(strict_types=1);

namespace Nusantara\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Nusantara\Data\VillageIndex;
use Nusantara\Models\District;
use Nusantara\Models\Province;
use Nusantara\Models\Regency;
use Nusantara\Models\Village;

class NusantaraSeeder extends Seeder
{
    /** Rows per upsert statement; 400 x 5 columns stays under SQL Server's 2100-parameter limit. */
    private const CHUNK_SIZE = 400;

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
            $this->seed('provinces', new Province(), ['name', 'latitude', 'longitude']);
        }
        if ($only === null || $only === 'regencies') {
            $this->seed('regencies', new Regency(), ['province_code', 'name', 'latitude', 'longitude']);
        }
        if ($only === null || $only === 'districts') {
            $this->seed('districts', new District(), ['regency_code', 'name', 'latitude', 'longitude']);
        }
        if ($only === null || $only === 'villages') {
            $this->seed('villages', new Village(), ['district_code', 'name', 'postal_code']);
        }
    }

    /**
     * Bulk upsert one level in a single transaction (one statement per chunk instead of
     * a SELECT + INSERT/UPDATE pair per row).
     *
     * @param array<int, string> $columns columns updated when the code already exists
     */
    private function seed(string $name, Model $model, array $columns): void
    {
        $file = $this->dataPath . DIRECTORY_SEPARATOR . $name . '.php';
        if (! is_file($file)) {
            return;
        }
        $data = require $file;
        if (is_string($data)) {
            $data = VillageIndex::fromData($data)->rows(); // villages.php line format, streamed row by row
        }
        if (! is_iterable($data)) {
            return;
        }

        // Build one chunk at a time (a full copy of the villages data alone is ~60 MB). Keying the
        // chunk by code drops duplicates inside a statement, which PostgreSQL rejects; across
        // chunks the upsert's update keeps "last row wins", as the old per-row updateOrInsert did.
        $model->getConnection()->transaction(function () use ($model, $data, $columns) {
            $chunk = [];
            foreach ($data as $row) {
                $record = ['code' => $row['code']];
                foreach ($columns as $column) {
                    $record[$column] = $row[$column] ?? null;
                }
                $chunk[$row['code']] = $record;

                if (count($chunk) >= self::CHUNK_SIZE) {
                    $model->newQuery()->upsert(array_values($chunk), ['code'], $columns);
                    $chunk = [];
                }
            }
            if ($chunk !== []) {
                $model->newQuery()->upsert(array_values($chunk), ['code'], $columns);
            }
        });
    }
}
