# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Unit tests for `Nusantara\Support\Cache` (get with TTL 0/TTL > 0, forget, flush, static key helpers).
- Unit tests for `Nusantara\Support\RegionCollection` (whereName, codes, names, toDropdown, titleCase, toTitleCase).
- Unit tests for `Nusantara\Enums\RegionLevel` (fromCode, label, edge cases).
- Unit tests for `Nusantara\Nusantara` (find, coordinates, regencies by name/code, postalCodes, hierarchy, edge cases).
- Feature tests for Artisan commands: `nusantara:install`, `nusantara:seed`, `nusantara:clear-cache`.
- PHPUnit coverage report configuration (text and HTML output).

### Changed

- **Cache:** `Cache::flush()` now performs a full flush when using a taggable cache store (Redis, Memcached). When using tags, `get()` and `forget()` use the `nusantara` tag so all keys can be cleared. For file/database cache drivers, only the `provinces` key is cleared (fallback).
- **CI:** Laravel 12.x added to the test matrix (PHP 8.2+).

### Fixed

- **Autoload:** `Nusantara\Database\Seeders\NusantaraSeeder` is now autoloaded via Composer `classmap` for `database/seeders`, fixing "Class not found" when running `php artisan nusantara:seed` in applications that require the package.

---

## [1.0.0] - (initial release)

- Indonesia region data (Kepmendagri 2025) with file or database driver.
- Provinces, regencies, districts, villages (hierarchical).
- Fuzzy search with Indonesian aliases (Jkt, Jaksel, SBY, etc.).
- Shipping address formatting (default, JNE, J&T, SiCepat, custom).
- Postal code lookup and validation.
- Coordinates and nearest regency (Haversine).
- Artisan commands: `nusantara:install`, `nusantara:seed`, `nusantara:update-data`, `nusantara:stats`, `nusantara:clear-cache`.
