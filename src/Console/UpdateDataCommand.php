<?php

declare(strict_types=1);

namespace Nusantara\Console;

use Illuminate\Console\Command;
use Nusantara\Data\VillageIndex;
use Nusantara\Support\Cache;

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
            if ($content === false || ! is_array(json_decode($content, true))) {
                $this->warn("Could not fetch a JSON array from {$url}; {$name} will not be updated.");
                continue;
            }
            file_put_contents($tempDir . DIRECTORY_SEPARATOR . $name . '.json', $content);
        }
        if (! is_file($tempDir . DIRECTORY_SEPARATOR . 'provinces.json')) {
            $this->removeDirectory($tempDir);
            $this->error('provinces.json could not be fetched; no data was changed.');
            return self::FAILURE;
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
        $header = "<?php\n\n// Source: Kepmendagri / nusantara:update-data\n// Last updated: " . date('Y-m-d') . "\n\n";
        $this->writeLevel($sourceDir, 'provinces', $this->normalizeProvinces(...), $header);
        $this->writeLevel($sourceDir, 'regencies', $this->normalizeRegencies(...), $header);
        $this->writeLevel($sourceDir, 'districts', $this->normalizeDistricts(...), $header);
        $this->writeLevel($sourceDir, 'villages', $this->normalizeVillages(...), $header);

        // Cached lists would otherwise keep serving the old data until their TTL expires.
        (new Cache())->flush();
        if (config('nusantara.driver') === 'database') {
            $this->line('Run `php artisan nusantara:seed` to load the new data into the database.');
        }
    }

    /** @param callable(array<int, array>): array<int, array> $normalize */
    private function writeLevel(string $sourceDir, string $name, callable $normalize, string $header): void
    {
        $path = $sourceDir . DIRECTORY_SEPARATOR . $name . '.json';
        if (! is_file($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) {
            return;
        }
        $out = $normalize($data);

        // Codes that collide after normalization (e.g. 7-digit BPS district codes such as 3273100 and
        // 3273101 both becoming 32.73.10) silently overwrite each other when seeding.
        $duplicates = count($out) - count(array_unique(array_column($out, 'code')));
        if ($duplicates > 0) {
            $this->warn("{$name}: {$duplicates} rows share a code with another row after normalization. "
                . 'The source probably uses BPS codes rather than Kepmendagri codes; use a Kepmendagri source.');
        }

        // Villages are stored as one sorted "<code>\t<NAME>\t<postal>" line each (see VillageIndex):
        // ~5 MB in memory instead of ~60 MB as arrays.
        $body = $name === 'villages'
            ? "// One village per line: \"<code>\\t<NAME>\\t<postal_code>\", sorted by code (read by Nusantara\\Data\\VillageIndex).\n"
                . 'return ' . VillageIndex::fromRows($out)->toPhpLiteral()
            : 'return ' . $this->exportRows($out);
        $this->writeAtomically($this->dataPath . DIRECTORY_SEPARATOR . $name . '.php', $header . $body . ";\n");
        $this->line(sprintf('  %s: %s rows', $name, number_format(count($out))));
    }

    /**
     * Write to a temp file and rename over the target, so concurrent requests never `require` a
     * half-written file, then drop the stale OPcache entry (matters when validate_timestamps=0).
     */
    private function writeAtomically(string $target, string $contents): void
    {
        $tmp = $target . '.' . uniqid('', true) . '.tmp';
        file_put_contents($tmp, $contents, LOCK_EX);
        rename($tmp, $target);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($target, true);
        }
    }

    /**
     * One row per line; roughly half the size of var_export()'s nested output, which keeps
     * parsing and OPcache memory down for the large villages file.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function exportRows(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $pairs = [];
            foreach ($row as $key => $value) {
                $pairs[] = var_export($key, true) . ' => ' . var_export($value, true);
            }
            $lines[] = '    [' . implode(', ', $pairs) . '],';
        }

        return "[\n" . implode("\n", $lines) . "\n]";
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
