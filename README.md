# Nusantara — Indonesia Region Data for Laravel

[![Tests](https://github.com/D1msky/nusantara/actions/workflows/tests.yml/badge.svg)](https://github.com/D1msky/nusantara/actions/workflows/tests.yml)
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
composer require d1msky/nusantara
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

Region data follows **Kepmendagri** (Indonesian Ministry of Home Affairs). Package ships with **sample data** so it works out of the box. For full datasets:

- Recommended: [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah) (Kepmendagri 2025)
- Alternative: [hanifabd/wilayah-indonesia-area](https://github.com/hanifabd/wilayah-indonesia-area) (JSON)

Use `php artisan nusantara:update-data --source=<path_or_base_url>` with a local folder or base URL that serves `provinces.json` (and optionally regencies, districts, villages). The command converts JSON to the PHP format expected by the package. See the command help and repo docs for details.

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
