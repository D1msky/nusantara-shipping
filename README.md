# Nusantara — Indonesia Region Data for Laravel

[![Tests](https://github.com/D1msky/nusantara-shipping/actions/workflows/tests.yml/badge.svg)](https://github.com/D1msky/nusantara-shipping/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Official Indonesian administrative region data (Kepmendagri 2025) for Laravel** — with shipping address formatting, postal code lookup, fuzzy search, and dual-mode storage (file or database). Built for Laravel apps **anywhere in the world** that need to handle Indonesian addresses (e.g. e‑commerce, logistics, forms).

---

## Why this package?

| Feature | Nusantara | Others |
|--------|-----------|--------|
| **Data** | Kepmendagri 2025 (updateable via command) | Often 2018–2022 |
| **Storage** | File (zero DB) or Database | Usually DB-only or file-only |
| **Shipping** | Address formatter, postal lookup, coordinates | Rare or basic |
| **Search** | Fuzzy + Indonesian aliases (Jkt, Jaksel, SBY, …) | Exact or simple like |
| **DX** | PHP 8.1+ enums, typed API, caching | Varies |

Use it for: dropdowns (provinces → regencies → districts → villages), address validation, shipping labels, postal code checks, and search that understands “Jaksel”, “Surabaya”, “Jogja”, etc.

---

## Requirements

- **PHP** 8.1+
- **Laravel** 10, 11, or 12
- **illuminate/support** & **illuminate/database** (no extra deps)

---

## Installation

```bash
composer require d1msky/nusantara-shipping
```

Publish config (optional):

```bash
php artisan nusantara:install
```

Or manually:

```bash
php artisan vendor:publish --tag=nusantara-config
```

For **database mode** (optional):

```bash
php artisan nusantara:install --migrate --seed
```

---

## Quick start

```php
use Nusantara\Facades\Nusantara;

// Hierarchical data (dropdowns)
$provinces = Nusantara::provinces();
$regencies = Nusantara::regencies('32');           // by province code
$regencies = Nusantara::regencies('JAWA BARAT');    // by province name
$districts = Nusantara::districts('32.73');
$villages  = Nusantara::villages('32.73.01');

// Find by code (any level)
$region = Nusantara::find('32.73.01.1001');

// Fuzzy search (handles typos and Indonesian abbreviations)
$results = Nusantara::search('jaksel');
$results = Nusantara::search('sby', level: 'regency');

// Postal code
$regions = Nusantara::postalCode('40132');
$valid   = Nusantara::validPostalCode('40132');
$codes   = Nusantara::postalCodes('32.73');

// Full hierarchy
$path = Nusantara::hierarchy('32.73.01.1001');
// ['province' => [...], 'regency' => [...], 'district' => [...], 'village' => [...]]

// Shipping address
$address = Nusantara::shippingAddress('32.73.01.1001');
$address = Nusantara::shippingAddress('32.73.01.1001', style: 'jne');

// Coordinates & nearest regency
$coords  = Nusantara::coordinates('32.73');
$nearest = Nusantara::nearestRegency(-6.2088, 106.8456);
```

---

## Configuration

Publish and edit `config/nusantara.php`:

| Option | Default | Description |
|--------|--------|-------------|
| `driver` | `file` | `file` (no DB) or `database` |
| `table_prefix` | `nusantara_` | Table prefix in database mode |
| `cache_ttl` | `86400` | Cache TTL (seconds); `0` = no cache |
| `cache_store` | `null` | Cache store (null = default) |
| `data_path` | package `data/` | Path to PHP data files (file driver) |
| `search.fuzzy_threshold` | `70` | Min similarity (0–100) for fuzzy match |
| `search.max_results` | `20` | Max search results |
| `aliases` | `[]` | Custom alias → region name for search |
| `shipping_styles` | `[]` | Custom courier formats |

Env example:

```env
NUSANTARA_DRIVER=file
NUSANTARA_CACHE_TTL=86400
NUSANTARA_DATA_PATH=
```

---

## Database mode

1. Set `NUSANTARA_DRIVER=database`.
2. Run migrations:
   ```bash
   php artisan migrate
   ```
3. Seed from package data files:
   ```bash
   php artisan nusantara:seed
   php artisan nusantara:seed --only=provinces
   ```

Tables: `nusantara_provinces`, `nusantara_regencies`, `nusantara_districts`, `nusantara_villages`.

---

## Artisan commands

| Command | Description |
|---------|-------------|
| `nusantara:install` | Publish config; optional `--migrate` and `--seed` |
| `nusantara:seed` | Seed DB from data files (`--only=provinces|regencies|districts|villages`) |
| `nusantara:update-data` | Show data source info; use `--source=<path_or_url>` to fetch and transform |
| `nusantara:stats` | Print counts (provinces, regencies, districts, villages, postal codes) |
| `nusantara:clear-cache` | Clear Nusantara cache |

---

## Extending

**Custom search aliases** (`config/nusantara.php`):

```php
'aliases' => [
    'mycity' => 'KOTA BANDUNG',
],
```

**Custom shipping style**:

```php
'shipping_styles' => [
    'my_courier' => [
        'format' => ':village, :district, :regency, :province, :postal',
        'case' => 'upper',
        'separator' => ', ',
    ],
],
```

Then: `Nusantara::shippingAddress($code, style: 'my_courier')`.

---

## Data source and updates

Region data follows **Kepmendagri** (Indonesian Ministry of Home Affairs). The package ships with **sample data** so it works out of the box. For full or up-to-date datasets, use the `nusantara:update-data` command.

### Command

```bash
php artisan nusantara:update-data --source=<path_or_base_url>
```

- **Without `--source`:** prints usage and recommended sources (no files changed).
- **With `--source`:** fetches or reads JSON from the given path/URL, converts it to the PHP format used by the package, and writes `provinces.php`, `regencies.php`, `districts.php`, and `villages.php` into the package `data/` folder (or `config('nusantara.data_path')` if set).

### Input: path or URL

| Type | Example | Description |
|------|---------|-------------|
| **Local folder** | `--source=/path/to/data` | Folder must contain at least `provinces.json`. Optional: `regencies.json`, `districts.json`, `villages.json`. |
| **Base URL** | `--source=https://example.com/data` | Command will request `{url}/provinces.json`, `{url}/regencies.json`, etc. Each must return a JSON array. |

For a **local folder**, the path must be absolute or relative to the current working directory. For a **URL**, use a base URL that serves the JSON files directly (e.g. raw GitHub or a CDN).

### Required and optional files

| File | Required | Description |
|------|----------|-------------|
| `provinces.json` | **Yes** | Array of province objects. |
| `regencies.json` | No | Array of regency/kabupaten/kota objects. |
| `districts.json` | No | Array of district/kecamatan objects. |
| `villages.json` | No | Array of village/kelurahan/desa objects. |

If a file is missing, that level is skipped; existing PHP data for that level is not overwritten.

### Expected JSON structure

Each file must be a **JSON array of objects**. The command normalizes common field names:

- **Provinces:** `code` / `id` / `kode`, `name` / `nama`; optional: `latitude`/`lat`, `longitude`/`lng`/`lon`.
- **Regencies:** `code` / `id` / `kode`, `name` / `nama`, `province_code` / `provinceCode` / `kode_provinsi` / `province_id` (or derived from code); optional: `latitude`, `longitude`.
- **Districts:** `code` / `id` / `kode`, `name` / `nama`, `regency_code` / `regencyCode` / `kode_kabupaten` (or derived from code); optional: `latitude`, `longitude`.
- **Villages:** `code` / `id` / `kode`, `name` / `nama`, `district_code` / `districtCode` / `kode_kecamatan` (or derived from code); optional: `postal_code` / `postalCode` / `kode_pos` / `zip`.

Codes can be dotted (e.g. `32.73`) or plain (e.g. `3273`); the command normalizes to dotted format where applicable.

### Example: URL (raw GitHub)

```bash
php artisan nusantara:update-data --source=https://raw.githubusercontent.com/yusufsyaifudin/wilayah-indonesia/master/data/list_of_area
```

This URL serves `provinces.json`, `regencies.json`, `districts.json`, and `villages.json` directly.

### Example: local folder

1. Clone a repo that contains JSON (e.g. in a `data` or `data/list_of_area` folder).
2. Run:

```bash
php artisan nusantara:update-data --source=/absolute/path/to/folder/with/provinces.json
# or, from project root:
php artisan nusantara:update-data --source=./vendor/some/repo/data
```

The folder must contain at least `provinces.json`.

### After updating data

- **File driver (`NUSANTARA_DRIVER=file`):** Data is read from the updated PHP files; no further step.
- **Database driver (`NUSANTARA_DRIVER=database`):** Run the seeder to fill the database from the updated files:

```bash
php artisan nusantara:seed
```

Then check counts:

```bash
php artisan nusantara:stats
```

### Recommended public sources

- [yusufsyaifudin/wilayah-indonesia](https://github.com/yusufsyaifudin/wilayah-indonesia) — JSON in `data/list_of_area/` (provinces, regencies, districts, villages).
- [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah) — Kepmendagri; provides SQL; for JSON you need to export or use a repo that exposes JSON.
- [hanifabd/wilayah-indonesia-area](https://github.com/hanifabd/wilayah-indonesia-area) — data in other formats (e.g. zip); use a source that exposes `.json` files if you want to use `--source=<url>` directly.

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

To generate a coverage report (requires PCOV or Xdebug):

```bash
vendor/bin/phpunit --coverage-text
# or
vendor/bin/phpunit --coverage-html build/coverage
```

---

## Contributing

Contributions are welcome. Please open an issue or PR on GitHub.

---

## License

MIT. See [LICENSE](LICENSE).
