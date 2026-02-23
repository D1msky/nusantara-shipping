<?php

declare(strict_types=1);

namespace Nusantara\Console;

use Illuminate\Console\Command;

class UpdateDataCommand extends Command
{
    protected $signature = 'nusantara:update-data
                            {--source= : Optional: local path to JSON/CSV folder or base URL for raw GitHub content}';

    protected $description = 'Update region data from official source (e.g. cahyadsn/wilayah or hanifabd/wilayah-indonesia-area)';

    private string $dataPath = '';

    public function handle(): int
    {
        $base = config('nusantara.data_path');
        $this->dataPath = $base !== null && $base !== ''
            ? rtrim($base, DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';

        $source = $this->option('source');
        if ($source) {
            return $this->fetchFromSource($source);
        }

        $this->info('To update data from an external source, run:');
        $this->line('  php artisan nusantara:update-data --source=<url_or_path>');
        $this->line('');
        $this->line('Recommended sources (Kepmendagri / public domain):');
        $this->line('  - https://github.com/cahyadsn/wilayah (Kepmendagri 2025)');
        $this->line('  - https://github.com/hanifabd/wilayah-indonesia-area (JSON)');
        $this->line('');
        $this->line('Package ships with built-in sample data. For full data, clone a source repo and pass the data folder path, or use a transformer script to convert JSON/CSV to the expected PHP array format (see README).');
        return self::SUCCESS;
    }

    private function fetchFromSource(string $source): int
    {
        if (is_dir($source)) {
            return $this->transformFromLocalPath($source);
        }
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            return $this->transformFromUrl($source);
        }
        $this->error('Source must be a directory path or a valid base URL.');
        return self::FAILURE;
    }

    private function transformFromLocalPath(string $path): int
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $provincesFile = $path . DIRECTORY_SEPARATOR . 'provinces.json';
        if (! is_file($provincesFile)) {
            $this->error("Expected {$provincesFile}. Ensure the source folder contains provinces.json (and optionally regencies, districts, villages).");
            return self::FAILURE;
        }
        $this->transformAndWrite($path);
        $this->info('Data updated from local path.');
        return self::SUCCESS;
    }

    private function transformFromUrl(string $baseUrl): int
    {
        $baseUrl = rtrim($baseUrl, '/');
        $files = ['provinces', 'regencies', 'districts', 'villages'];
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nusantara_update_' . uniqid();
        if (! @mkdir($tempDir, 0755, true)) {
            $this->error('Could not create temp directory.');
            return self::FAILURE;
        }
        foreach ($files as $name) {
            $url = $baseUrl . '/' . $name . '.json';
            $content = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 30],
                'ssl' => ['verify_peer' => true],
            ]));
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    file_put_contents($tempDir . DIRECTORY_SEPARATOR . $name . '.json', $content);
                }
            }
        }
        $this->transformAndWrite($tempDir);
        $this->removeDirectory($tempDir);
        $this->info('Data updated from URL.');
        return self::SUCCESS;
    }

    private function transformAndWrite(string $sourceDir): void
    {
        if (! is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
        $header = "<?php\n\n// Source: Kepmendagri / nusantara:update-data\n// Last updated: " . date('Y-m-d') . "\n\nreturn ";
        $this->writeProvinces($sourceDir, $header);
        $this->writeRegencies($sourceDir, $header);
        $this->writeDistricts($sourceDir, $header);
        $this->writeVillages($sourceDir, $header);
    }

    private function writeProvinces(string $sourceDir, string $header): void
    {
        $path = $sourceDir . DIRECTORY_SEPARATOR . 'provinces.json';
        if (! is_file($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) {
            return;
        }
        $out = $this->normalizeProvinces($data);
        file_put_contents($this->dataPath . DIRECTORY_SEPARATOR . 'provinces.php', $header . var_export($out, true) . ";\n");
    }

    private function writeRegencies(string $sourceDir, string $header): void
    {
        $path = $sourceDir . DIRECTORY_SEPARATOR . 'regencies.json';
        if (! is_file($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) {
            return;
        }
        $out = $this->normalizeRegencies($data);
        file_put_contents($this->dataPath . DIRECTORY_SEPARATOR . 'regencies.php', $header . var_export($out, true) . ";\n");
    }

    private function writeDistricts(string $sourceDir, string $header): void
    {
        $path = $sourceDir . DIRECTORY_SEPARATOR . 'districts.json';
        if (! is_file($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) {
            return;
        }
        $out = $this->normalizeDistricts($data);
        file_put_contents($this->dataPath . DIRECTORY_SEPARATOR . 'districts.php', $header . var_export($out, true) . ";\n");
    }

    private function writeVillages(string $sourceDir, string $header): void
    {
        $path = $sourceDir . DIRECTORY_SEPARATOR . 'villages.json';
        if (! is_file($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) {
            return;
        }
        $out = $this->normalizeVillages($data);
        file_put_contents($this->dataPath . DIRECTORY_SEPARATOR . 'villages.php', $header . var_export($out, true) . ";\n");
    }

    /** @param array<int, array> $data */
    private function normalizeProvinces(array $data): array
    {
        $out = [];
        foreach ($data as $row) {
            $code = $this->extractCode($row, 'code', 'id', 'kode');
            $name = $this->extractName($row);
            if ($code === null || $name === null) {
                continue;
            }
            $out[] = [
                'code' => (string) $code,
                'name' => mb_strtoupper($name),
                'latitude' => $this->extractLat($row),
                'longitude' => $this->extractLon($row),
            ];
        }
        return $out;
    }

    /** @param array<int, array> $data */
    private function normalizeRegencies(array $data): array
    {
        $out = [];
        foreach ($data as $row) {
            $code = $this->extractRegencyCode($row);
            $provinceCode = $this->extractProvinceCode($row);
            $name = $this->extractName($row);
            if ($code === null || $provinceCode === null || $name === null) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'province_code' => $provinceCode,
                'name' => mb_strtoupper($name),
                'latitude' => $this->extractLat($row),
                'longitude' => $this->extractLon($row),
            ];
        }
        return $out;
    }

    /** @param array<int, array> $data */
    private function normalizeDistricts(array $data): array
    {
        $out = [];
        foreach ($data as $row) {
            $code = $this->extractDistrictCode($row);
            $regencyCode = $this->extractRegencyCodeFromRow($row);
            $name = $this->extractName($row);
            if ($code === null || $regencyCode === null || $name === null) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'regency_code' => $regencyCode,
                'name' => mb_strtoupper($name),
                'latitude' => $this->extractLat($row),
                'longitude' => $this->extractLon($row),
            ];
        }
        return $out;
    }

    /** @param array<int, array> $data */
    private function normalizeVillages(array $data): array
    {
        $out = [];
        foreach ($data as $row) {
            $code = $this->extractVillageCode($row);
            $districtCode = $this->extractDistrictCodeFromRow($row);
            $name = $this->extractName($row);
            if ($code === null || $districtCode === null || $name === null) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'district_code' => $districtCode,
                'name' => mb_strtoupper($name),
                'postal_code' => $this->extractPostalCode($row),
            ];
        }
        return $out;
    }

    private function extractCode(array $row, string ...$keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($row[$k])) {
                $v = $row[$k];
                return is_string($v) ? $v : (string) $v;
            }
        }
        return null;
    }

    private function extractName(array $row): ?string
    {
        $v = $this->extractCode($row, 'name', 'nama');
        return $v;
    }

    private function extractLat(array $row): ?float
    {
        $v = $row['latitude'] ?? $row['lat'] ?? null;
        return $v !== null ? (float) $v : null;
    }

    private function extractLon(array $row): ?float
    {
        $v = $row['longitude'] ?? $row['lng'] ?? $row['lon'] ?? null;
        return $v !== null ? (float) $v : null;
    }

    private function extractProvinceCode(array $row): ?string
    {
        $v = $row['province_code'] ?? $row['provinceCode'] ?? $row['kode_provinsi'] ?? $row['province_id'] ?? null;
        if ($v !== null) {
            return (string) $v;
        }
        $code = $this->extractRegencyCode($row);
        return $code !== null ? explode('.', $code)[0] ?? null : null;
    }

    private function extractRegencyCode(array $row): ?string
    {
        $v = $row['code'] ?? $row['id'] ?? $row['kode'] ?? null;
        if ($v !== null) {
            $s = (string) $v;
            return str_contains($s, '.') ? $s : (strlen($s) >= 4 ? substr($s, 0, 2) . '.' . substr($s, 2, 2) : $s);
        }
        return null;
    }

    private function extractRegencyCodeFromRow(array $row): ?string
    {
        $v = $row['regency_code'] ?? $row['regencyCode'] ?? $row['kode_kabupaten'] ?? null;
        if ($v !== null) {
            return (string) $v;
        }
        $code = $this->extractDistrictCode($row);
        return $code !== null ? implode('.', array_slice(explode('.', $code), 0, 2)) : null;
    }

    private function extractDistrictCode(array $row): ?string
    {
        $v = $row['code'] ?? $row['id'] ?? $row['kode'] ?? null;
        if ($v !== null) {
            $s = (string) $v;
            if (str_contains($s, '.')) {
                return $s;
            }
            if (strlen($s) >= 6) {
                return substr($s, 0, 2) . '.' . substr($s, 2, 2) . '.' . substr($s, 4, 2);
            }
            return $s;
        }
        return null;
    }

    private function extractDistrictCodeFromRow(array $row): ?string
    {
        $v = $row['district_code'] ?? $row['districtCode'] ?? $row['kode_kecamatan'] ?? null;
        if ($v !== null) {
            return (string) $v;
        }
        $code = $this->extractVillageCode($row);
        return $code !== null ? implode('.', array_slice(explode('.', $code), 0, 3)) : null;
    }

    private function extractVillageCode(array $row): ?string
    {
        $v = $row['code'] ?? $row['id'] ?? $row['kode'] ?? null;
        if ($v !== null) {
            $s = (string) $v;
            if (str_contains($s, '.')) {
                return $s;
            }
            if (strlen($s) >= 10) {
                return substr($s, 0, 2) . '.' . substr($s, 2, 2) . '.' . substr($s, 4, 2) . '.' . substr($s, 6, 4);
            }
            return $s;
        }
        return null;
    }

    private function extractPostalCode(array $row): ?string
    {
        $v = $row['postal_code'] ?? $row['postalCode'] ?? $row['kode_pos'] ?? $row['zip'] ?? null;
        return $v !== null ? (string) $v : null;
    }

    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            is_dir($f) ? $this->removeDirectory($f) : @unlink($f);
        }
        @rmdir($dir);
    }
}
