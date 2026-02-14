# Nusantara — Indonesia Region Data for Laravel

[![Tests](https://github.com/dimasdev/nusantara/actions/workflows/tests.yml/badge.svg)](https://github.com/dimasdev/nusantara/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/dimasdev/nusantara/v/stable)](https://packagist.org/packages/dimasdev/nusantara)
[![Total Downloads](https://poser.pugx.org/dimasdev/nusantara/downloads)](https://packagist.org/packages/dimasdev/nusantara)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Official Indonesian administrative region data (Kepmendagri 2025) for Laravel** — with shipping address formatting, postal code lookup, fuzzy search, and dual-mode storage (file or database). Built for Laravel apps **anywhere in the world** that need to handle Indonesian addresses (e.g. e-commerce, logistics, forms).

---

## Why this package?

| Feature | Nusantara | Others |
|--------|-----------|--------|
| **Data** | Kepmendagri 2025 (updateable via command) | Often 2018-2022 |
| **Storage** | File (zero DB) or Database | Usually DB-only or file-only |
| **Shipping** | Address formatter for JNE, JNT, SiCepat, Pos Indonesia, Lion Parcel | Rare or basic |
| **Search** | Fuzzy + 40+ Indonesian aliases (Jkt, Jaksel, SBY, Jogja, Solo, ...) | Exact or simple LIKE |
| **DX** | PHP 8.1+ enums, typed API, comprehensive caching | Varies |

Use it for: dropdowns (provinces -> regencies -> districts -> villages), address validation, shipping labels, postal code checks, and search that understands "Jaksel", "Surabaya", "Jogja", etc.

---

## Requirements

- **PHP** 8.1+
- **Laravel** 10, 11, or 12
- **illuminate/support** & **illuminate/database** (no extra deps)

---

## Installation

```bash
composer require dimasdev/nusantara
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
use Nusantara\NusantaraFacade as Nusantara;

// Hierarchical data (dropdowns)
$provinces = Nusantara::provinces();
$regencies = Nusantara::regencies('32');           // by province code
$regencies = Nusantara::regencies('JAWA BARAT');   // by province name
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

## Usage

### Basic lookups

```php
// All provinces
$provinces = Nusantara::provinces();
// Returns RegionCollection of ['code', 'name', 'latitude', 'longitude']

// Regencies in a province
$cities = Nusantara::regencies('32');            // Jawa Barat
$cities = Nusantara::regencies('JAWA BARAT');    // also works by name

// Districts in a regency
$districts = Nusantara::districts('32.73');       // Kota Bandung

// Villages in a district
$villages = Nusantara::villages('32.73.01');      // Bandung Wetan

// Find specific region by code (auto-detects level)
$province = Nusantara::find('32');
$village  = Nusantara::find('32.73.01.1001');
```

### Search

```php
// Basic search (searches all levels)
$results = Nusantara::search('bandung');

// Search specific level
$results = Nusantara::search('bandung', level: 'regency');

// Fuzzy search handles abbreviations
$results = Nusantara::search('jkt selatan');  // -> Jakarta Selatan
$results = Nusantara::search('sby');          // -> Surabaya
$results = Nusantara::search('jogja');        // -> DI Yogyakarta
$results = Nusantara::search('solo');         // -> Surakarta
$results = Nusantara::search('jaksel');       // -> Jakarta Selatan
```

### Postal codes

```php
// Find regions by postal code
$regions = Nusantara::postalCode('40132');

// Validate postal code
$valid = Nusantara::validPostalCode('40132');  // true
$valid = Nusantara::validPostalCode('99999');  // false

// Get all postal codes for a regency/district
$codes = Nusantara::postalCodes('32.73');
```

### Hierarchy

```php
$path = Nusantara::hierarchy('32.73.01.1001');
// [
//     'province'  => ['code' => '32', 'name' => 'JAWA BARAT'],
//     'regency'   => ['code' => '32.73', 'name' => 'KOTA BANDUNG'],
//     'district'  => ['code' => '32.73.01', 'name' => 'BANDUNG WETAN'],
//     'village'   => ['code' => '32.73.01.1001', 'name' => 'CIHAPIT'],
// ]
```

### Shipping address formatting

```php
// Default format (title case)
$address = Nusantara::shippingAddress('32.73.01.1001');
// "Cihapit, Bandung Wetan, Kota Bandung, Jawa Barat, 40114"

// Custom template
$address = Nusantara::shippingAddress('32.73.01.1001', format: ':village, :district, :regency :postal');
// "Cihapit, Bandung Wetan, Kota Bandung 40114"

// Courier-specific styles
$address = Nusantara::shippingAddress('32.73.01.1001', style: 'jne');
// "CIHAPIT, BANDUNG WETAN, KOTA BANDUNG, 40114"

$address = Nusantara::shippingAddress('32.73.01.1001', style: 'sicepat');
// "CIHAPIT, BANDUNG WETAN, KOTA BANDUNG, JAWA BARAT, 40114"
```

Built-in styles: `default`, `jne`, `jnt`, `sicepat`, `pos_indonesia`, `lion_parcel`.

### Coordinate utilities

```php
// Get coordinates
$coords = Nusantara::coordinates('32.73');
// ['latitude' => -6.9174639, 'longitude' => 107.6191228]

// Find nearest regency from coordinates
$nearest = Nusantara::nearestRegency(-6.2088, 106.8456);
// ['code' => '31.71', 'name' => 'KOTA JAKARTA SELATAN', 'distance_km' => 2.3]
```

### RegionCollection

All list methods return `RegionCollection` (extends Laravel Collection):

```php
$provinces = Nusantara::provinces();

// Filter by name
$filtered = $provinces->whereName('jawa');

// Get codes or names only
$codes = $provinces->codes();    // ['11', '31', '32', ...]
$names = $provinces->names();    // ['ACEH', 'DKI JAKARTA', ...]

// For <select> dropdowns
$dropdown = $provinces->toDropdown();
// ['11' => 'ACEH', '31' => 'DKI JAKARTA', ...]

// Title case conversion
$titled = $provinces->titleCase();
// 'JAWA BARAT' -> 'Jawa Barat'
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
| `search.fuzzy_threshold` | `70` | Min similarity (0-100) for fuzzy match |
| `search.max_results` | `20` | Max search results |
| `aliases` | `[]` | Custom alias -> region name for search |
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
| `nusantara:seed` | Seed DB from data files (`--only=provinces\|regencies\|districts\|villages`) |
| `nusantara:update-data` | Fetch and transform data; use `--source=<path_or_url>` |
| `nusantara:stats` | Print counts (provinces, regencies, districts, villages, postal codes) |
| `nusantara:clear-cache` | Clear all Nusantara cache entries |

---

## Extending

**Custom search aliases** (`config/nusantara.php`):

```php
'aliases' => [
    'mycity' => 'KOTA BANDUNG',
    'bali'   => 'BALI',
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

Region data follows **Kepmendagri No 300.2.2-2138 Tahun 2025** (Indonesian Ministry of Home Affairs). Package ships with **sample data** so it works out of the box. For full datasets:

- Primary: [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah) (Kepmendagri 2025)
- Supplementary: [hanifabd/wilayah-indonesia-area](https://github.com/hanifabd/wilayah-indonesia-area) (JSON)

Use `php artisan nusantara:update-data --source=<path_or_base_url>` with a local folder or base URL that serves `provinces.json` (and optionally regencies, districts, villages). The command converts JSON to the PHP format expected by the package.

**Credits**: Data sourced from Kementerian Dalam Negeri Republik Indonesia, [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah), and BPS (Badan Pusat Statistik).

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests cover: FileRepository, DatabaseRepository, FuzzyMatcher, AddressFormatter, PostalCodeResolver, Hierarchy, and Facade integration.

---

## Contributing

Contributions are welcome! Here's how:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes and add tests
4. Ensure all tests pass (`vendor/bin/phpunit`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

Please make sure your code follows the existing style and all tests pass before submitting.

---

## License

MIT. See [LICENSE](LICENSE).
