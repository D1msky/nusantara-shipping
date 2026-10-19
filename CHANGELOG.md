# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-10-19

First 2.x release: contains everything listed under 2.0.0 below, plus these fixes. Upgrading from 1.x? Read [UPGRADE.md](UPGRADE.md) first — region codes changed.

### Fixed

- Postal-code lookups returned nothing when `data/villages.php` had CRLF line endings (e.g. a git checkout on Windows with `core.autocrlf=true`): `VillageIndex` now normalizes line endings, and `.gitattributes` keeps `data/*.php` at LF.
- **CI:** Laravel 10 and 11 jobs failed to install because Composer ≥ 2.9 blocks releases with security advisories and those versions are end-of-life; the jobs now set `policy.advisories.block` to `false`.

## [2.0.0] - 2026-10-19 [YANKED]

Withdrawn: postal-code lookups failed when the data files were checked out with CRLF line endings. Use 2.0.1, which includes all of the changes below.

Region codes changed to the official Kepmendagri codes — see [UPGRADE.md](UPGRADE.md) before upgrading.

### Breaking

- **Data:** bundled data replaced with the full Kepmendagri No 300.2.2-2138 Tahun 2025 dataset from cahyadsn/wilayah and cahyadsn/wilayah_kodepos: 38 provinces, 514 regencies, 7,285 districts, 83,762 villages, all villages with a postal code. The 1.x data was BPS-coded (199 of 514 regency codes differ; 1,520 district codes were shared by several districts, so only 4,571 of 7,071 districts survived seeding), had 34 provinces and 6 sample villages. Stored codes must be migrated — UPGRADE.md lists every changed regency code.
- **Names** follow Kepmendagri spelling (`DAERAH KHUSUS IBUKOTA JAKARTA`, `KOTA ADMINISTRASI JAKARTA SELATAN`, `DAERAH ISTIMEWA YOGYAKARTA`, …); the old names remain search aliases.
- **District coordinates** are only kept where the carried-over point is unique and inside the district's regency (5,619 of 7,285); the 1.x points were geocoded by name (every "SUKASARI" sat in Kota Bogor). `coordinates()` falls back to the regency for the rest.
- The migration is only registered when `NUSANTARA_DRIVER=database` (file-driver apps no longer get four empty tables on `migrate`). `nusantara:install --migrate` fails for other drivers and runs only the Nusantara migration instead of every pending app migration.
- `data/villages.php` stores one `"<code>\t<NAME>\t<postal_code>"` line per village (read by the new `Nusantara\Data\VillageIndex`): 2.5 MB on disk instead of 9.5 MB, ~5 MB in memory instead of ~60 MB, and lookups by district/code use binary search. The old array format is still accepted from a custom `data_path`.

### Added

- Unit tests for `Nusantara\Support\Cache` (get with TTL 0/TTL > 0, forget, flush, static key helpers).
- Unit tests for `Nusantara\Support\RegionCollection` (whereName, codes, names, toDropdown, titleCase, toTitleCase).
- Unit tests for `Nusantara\Enums\RegionLevel` (fromCode, label, edge cases).
- Unit tests for `Nusantara\Nusantara` (find, coordinates, regencies by name/code, postalCodes, hierarchy, edge cases).
- Feature tests for Artisan commands: `nusantara:install`, `nusantara:seed`, `nusantara:clear-cache`.
- PHPUnit coverage report configuration (text and HTML output).
- `Nusantara\Facades\Nusantara` facade (the class the README already documented); `Nusantara\NusantaraFacade` remains as a deprecated alias.
- `RepositoryInterface::allRegencies()` and `allDistricts()` for full scans.
- `nusantara:update-data` warns when codes collide after normalization (e.g. BPS 7-digit district codes).
- Tests for `nusantara:update-data`, `nusantara:stats`, `VillageIndex`, migration registration per driver, cache invalidation on non-taggable stores, and file-driver results with caching enabled.
- `data/NOTICE.md` with data sources and their MIT notices; `UPGRADE.md`; `tools/convert-cahyadsn.php` to rebuild the data from the cahyadsn SQL dumps.
- Search aliases for pre-2025 names (`dki`, `dki jakarta`, `diy`, `di yogyakarta`, `kota jakarta selatan`, …).

### Changed

- **Cache:** keys are versioned; `Cache::flush()` (and `nusantara:clear-cache`) bumps the version, which invalidates every entry on **any** store (file, database, redis, …) without needing tags. Lookups are also memoized in-process, so repeated reads in one request no longer hit the cache store.
- **Performance:** fuzzy search and `nearestRegency()` read regencies/districts in one pass instead of one cache read per province/regency (fuzzy search with a typo: ~587 → 4 cache reads); fuzzy ranking scores each candidate once instead of inside the sort comparator.
- **Performance:** `NusantaraSeeder` bulk-upserts in chunks inside a transaction instead of `updateOrInsert` per row, building one chunk at a time (full dataset: 232 queries, 44 MB peak memory).
- **Performance:** the file driver keeps parsed data files per process (keyed on mtime + size), so Octane/queue workers don't re-read them on every cache miss.
- **Database driver:** `find*()` results are cached; coordinates are cast to `float` (as in the file driver); search is case-insensitive on every database (PostgreSQL `LIKE` is case-sensitive); postal-code results no longer embed nested relations, matching the file driver's shape.
- `Nusantara` is bound as `scoped` instead of `singleton`, so per-request memoization is safe under Octane and queue workers.
- `nusantara:update-data` writes files atomically, invalidates OPcache, flushes the Nusantara cache, writes one row per line, and fails (instead of reporting success) when `provinces.json` cannot be fetched.
- **CI:** Laravel 12.x added to the test matrix (PHP 8.2+); PHP 8.4 added; Composer cache keyed per matrix entry.

### Fixed

- **Autoload:** `Nusantara\Database\Seeders\NusantaraSeeder` is now autoloaded via Composer `classmap` for `database/seeders`, fixing "Class not found" when running `php artisan nusantara:seed` in applications that require the package.
- Custom search aliases now override built-in aliases regardless of letter case (`'JKT'` vs `'jkt'`).
- `search($term, 'District')` (non-lower-case level) no longer returns an empty fuzzy result.

---

## [1.0.0] - (initial release)

- Indonesia region data (Kepmendagri 2025) with file or database driver.
- Provinces, regencies, districts, villages (hierarchical).
- Fuzzy search with Indonesian aliases (Jkt, Jaksel, SBY, etc.).
- Shipping address formatting (default, JNE, J&T, SiCepat, custom).
- Postal code lookup and validation.
- Coordinates and nearest regency (Haversine).
- Artisan commands: `nusantara:install`, `nusantara:seed`, `nusantara:update-data`, `nusantara:stats`, `nusantara:clear-cache`.
