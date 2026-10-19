<?php

/**
 * Convert cahyadsn/wilayah + cahyadsn/wilayah_kodepos SQL dumps into the JSON layout that
 * `php artisan nusantara:update-data --source=<dir>` understands.
 *
 * Usage: php tools/convert-cahyadsn.php <wilayah.sql> <wilayah_level_1_2.sql> <wilayah_kodepos.sql> <old-data-dir> <out-dir>
 *        then: php artisan nusantara:update-data --source=<out-dir>
 *
 * Inputs: db/wilayah.sql and db/wilayah_level_1_2.sql from https://github.com/cahyadsn/wilayah,
 * db/wilayah_kodepos.sql from https://github.com/cahyadsn/wilayah_kodepos. <old-data-dir> is a
 * directory with the previous regencies.php/districts.php (normally this package's data/): the
 * sources have no district coordinates, so they are carried over by regency + district name.
 *
 * The SQL files are only read as text (regex), never executed.
 */

[$_, $wilayahSql, $levelSql, $kodeposSql, $oldDir, $outDir] = $argv + array_fill(0, 6, null);
foreach ([$wilayahSql, $levelSql, $kodeposSql] as $f) {
    if (! is_file((string) $f)) {
        fwrite(STDERR, "missing input: $f\n");
        exit(1);
    }
}

const STR = "'((?:[^'\\\\]|\\\\.|'')*)'";

function unquote(string $s): string
{
    return strtr($s, ["\\'" => "'", "''" => "'", '\\\\' => '\\', '\\"' => '"']);
}

function norm(string $name): string
{
    return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($name));
}

// 1) Codes + names (all four levels share one table, level = code shape)
preg_match_all('/^\(' . STR . ',\s*' . STR . '\)[,;]\s*$/m', file_get_contents($wilayahSql), $m, PREG_SET_ORDER);
$levels = ['provinces' => [], 'regencies' => [], 'districts' => [], 'villages' => []];
foreach ($m as [, $code, $name]) {
    $name = trim(unquote($name));
    $parts = explode('.', $code);
    $ok = match (count($parts)) {
        1 => preg_match('/^\d{2}$/', $code),
        2 => preg_match('/^\d{2}\.\d{2}$/', $code),
        3 => preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $code),
        4 => preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/', $code),
        default => 0,
    };
    if (! $ok) {
        fwrite(STDERR, "unexpected code shape: $code\n");
        exit(1);
    }
    $levels[array_keys($levels)[count($parts) - 1]][$code] = $name;
}

// 2) Province/regency coordinates
$num = '(-?\d+(?:\.\d+)?(?:[eE][-+]?\d+)?|NULL)';
preg_match_all('/\(' . STR . ',\s*' . STR . ',\s*(?:' . STR . '|NULL),\s*' . $num . ',\s*' . $num . ',\s*' . $num . ',\s*' . $num . ',\s*' . $num . '/', file_get_contents($levelSql), $m, PREG_SET_ORDER);
$coords = [];
$area = [];
foreach ($m as $row) {
    [$lat, $lng, , , $luas] = array_slice($row, 4, 5) + array_fill(0, 5, 'NULL');
    if ($lat !== 'NULL' && $lng !== 'NULL') {
        $coords[$row[1]] = [round((float) $lat, 6), round((float) $lng, 6)];
    }
    if ($luas !== 'NULL') {
        $area[$row[1]] = (float) $luas;
    }
}

function km(array $a, array $b): float
{
    $dLat = deg2rad($b[0] - $a[0]);
    $dLon = deg2rad($b[1] - $a[1]);
    $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLon / 2) ** 2;

    return 6371 * 2 * atan2(sqrt($h), sqrt(1 - $h));
}

// 3) Postal codes per village
preg_match_all('/\(' . STR . ',\s*' . STR . '\)/', file_get_contents($kodeposSql), $m, PREG_SET_ORDER);
$postal = [];
foreach ($m as [, $code, $pc]) {
    if (preg_match('/^\d{5}$/', $pc)) {
        $postal[$code] = $pc;
    }
}

// 4) District coordinates from the previous (BPS-coded) data, matched by regency + district name
$oldRegencies = require $oldDir . '/regencies.php';
$oldDistricts = require $oldDir . '/districts.php';

// Old (BPS) regency code -> new (Kepmendagri) regency code, by name. Codes cannot be used
// directly: BPS 31.71 is Jakarta Selatan, Kepmendagri 31.71 is Jakarta Pusat.
$renamed = [
    'KABUPATENTOBASAMOSIR' => '12.12', 'KOTAPADANGSIDEMPUAN' => '12.77', 'KABUPATENKEPULAUANSERIBU' => '31.01',
    'KOTAJAKARTAPUSAT' => '31.71', 'KOTAJAKARTAUTARA' => '31.72', 'KOTAJAKARTABARAT' => '31.73',
    'KOTAJAKARTASELATAN' => '31.74', 'KOTAJAKARTATIMUR' => '31.75', 'KABUPATENMAHAKAMHULU' => '64.11',
    'KABUPATENSIAUTAGULANDANGBIARO' => '71.09', 'KABUPATENMAMUJUUTARA' => '76.01', 'KABUPATENMALUKUTENGGARABARAT' => '81.03',
];
$newRegencyByName = [];
foreach ($levels['regencies'] as $code => $name) {
    $newRegencyByName[norm($name)] = $code;
}
$oldToNewRegency = [];
foreach ($oldRegencies as $r) {
    $n = norm($r['name']);
    $new = $newRegencyByName[$n] ?? $renamed[$n] ?? null;
    if ($new === null) {
        fwrite(STDERR, "unmapped old regency: {$r['code']} {$r['name']}\n");
        exit(1);
    }
    $oldToNewRegency[$r['code']] = $new;
}
// The old coordinates were evidently geocoded by name alone (every "SUKASARI" sits in Kota Bogor),
// so a point used by more than one old district is never trusted.
$pointUse = [];
foreach ($oldDistricts as $d) {
    if (isset($d['latitude'], $d['longitude'])) {
        $pointUse[$d['latitude'] . ',' . $d['longitude']] = ($pointUse[$d['latitude'] . ',' . $d['longitude']] ?? 0) + 1;
    }
}
$oldDistrictCoords = [];
$rejectedShared = 0;
foreach ($oldDistricts as $d) {
    if (isset($d['latitude'], $d['longitude'])) {
        if ($pointUse[$d['latitude'] . ',' . $d['longitude']] > 1) {
            $rejectedShared++;
            continue;
        }
        $key = $oldToNewRegency[$d['regency_code']] . '|' . norm($d['name']);
        $oldDistrictCoords[$key][] = [(float) $d['latitude'], (float) $d['longitude']];
    }
}

// ---- Emit JSON ----
@mkdir($outDir, 0755, true);
$stats = [];

$out = [];
foreach ($levels['provinces'] as $code => $name) {
    $out[] = ['code' => $code, 'name' => $name, 'latitude' => $coords[$code][0] ?? null, 'longitude' => $coords[$code][1] ?? null];
}
$stats['provinces'] = [count($out), count(array_filter($out, fn ($r) => $r['latitude'] !== null))];
file_put_contents("$outDir/provinces.json", json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

$out = [];
foreach ($levels['regencies'] as $code => $name) {
    $out[] = ['code' => $code, 'province_code' => substr($code, 0, 2), 'name' => $name, 'latitude' => $coords[$code][0] ?? null, 'longitude' => $coords[$code][1] ?? null];
}
$stats['regencies'] = [count($out), count(array_filter($out, fn ($r) => $r['latitude'] !== null))];
file_put_contents("$outDir/regencies.json", json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

$out = [];
$ambiguous = 0;
$rejectedFar = 0;
foreach ($levels['districts'] as $code => $name) {
    $regencyCode = substr($code, 0, 5);
    $key = $regencyCode . '|' . norm($name);
    $candidates = $oldDistrictCoords[$key] ?? [];
    if (count($candidates) > 1) {
        $ambiguous++;
        $candidates = []; // same name twice in one regency: don't guess
    }
    // Must lie plausibly inside its (new) regency: within twice the radius of a circle of the
    // regency's area, plus 10 km slack because the regency point is its capital, not its centroid.
    if ($candidates !== [] && isset($coords[$regencyCode])) {
        $radius = sqrt(($area[$regencyCode] ?? 0) / M_PI);
        if (km($candidates[0], $coords[$regencyCode]) > 2 * $radius + 10) {
            $rejectedFar++;
            $candidates = [];
        }
    }
    $out[] = ['code' => $code, 'regency_code' => $regencyCode, 'name' => $name, 'latitude' => $candidates[0][0] ?? null, 'longitude' => $candidates[0][1] ?? null];
}
$stats['districts'] = [count($out), count(array_filter($out, fn ($r) => $r['latitude'] !== null)), "ambiguous=$ambiguous rejected-shared-point=$rejectedShared rejected-too-far=$rejectedFar regencies-with-area=" . count(array_intersect_key($area, $levels['regencies']))];
file_put_contents("$outDir/districts.json", json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

$out = [];
foreach ($levels['villages'] as $code => $name) {
    $out[] = ['code' => $code, 'district_code' => substr($code, 0, 8), 'name' => $name, 'postal_code' => $postal[$code] ?? null];
}
$stats['villages'] = [count($out), count(array_filter($out, fn ($r) => $r['postal_code'] !== null))];
file_put_contents("$outDir/villages.json", json_encode($out, JSON_UNESCAPED_UNICODE));

$orphanPostal = count(array_diff_key($postal, $levels['villages']));
foreach ($stats as $level => $s) {
    printf("%-10s rows=%d with-coords-or-postal=%s %s\n", $level, $s[0], $s[1], $s[2] ?? '');
}
printf("postal rows without matching village: %d\n", $orphanPostal);
