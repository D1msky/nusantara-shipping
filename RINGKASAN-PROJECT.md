# Ringkasan Project: Nusantara

**Bahan diskusi dengan AI** — ringkasan singkat untuk konteks project.

---

## Apa ini?

**Nusantara** adalah paket Laravel yang menyediakan **data wilayah administratif Indonesia** (Kepmendagri 2025) dengan fitur siap pakai untuk form alamat, shipping, kode pos, dan pencarian fuzzy.

- **Package:** `d1msky/nusantara`
- **Stack:** PHP 8.1+, Laravel 10/11/12
- **Lisensi:** MIT

---

## Fitur utama

| Area | Fitur |
|------|--------|
| **Data** | Provinsi → Kabupaten/Kota → Kecamatan → Kelurahan/Desa (hierarki lengkap) |
| **Storage** | Dual mode: **file** (tanpa DB) atau **database** (4 tabel: provinces, regencies, districts, villages) |
| **Shipping** | Formatter alamat pengiriman, style per kurir (JNE, custom), lookup kode pos |
| **Search** | Fuzzy search + alias Indonesia (Jaksel, SBY, Jogja, dll.) |
| **Lain** | Koordinat, hierarchy path, validasi kode pos, nearest regency |

---

## Arsitektur singkat

- **Driver:** `file` (PHP data di `data/`) atau `database` (migration + seed)
- **Repository:** `RepositoryInterface` → `FileRepository` / `DatabaseRepository`
- **Komponen:** `Nusantara` (facade), `AddressFormatter`, `PostalCodeResolver`, `FuzzyMatcher`, `RegionCollection`, enum `RegionLevel`
- **Commands:** `nusantara:install`, `nusantara:seed`, `nusantara:update-data`, `nusantara:stats`, `nusantara:clear-cache`

---

## Use case

- Dropdown bertingkat (provinsi → kabupaten → kecamatan → kelurahan)
- Validasi & format alamat pengiriman
- Cek kode pos, label shipping
- Pencarian yang paham singkatan/alias Indonesia

---

## Poin diskusi yang bisa digali

1. **Data:** Sumber Kepmendagri; update via `nusantara:update-data` (JSON → format PHP).
2. **Performa:** Cache (TTL konfigurasi), optional DB index.
3. **Ekstensibilitas:** Alias search & custom shipping style lewat config.
4. **Testing:** PHPUnit (Unit + Feature); testbench untuk Laravel.

---

*Dibuat sebagai bahan diskusi dengan AI. Detail lengkap di [README.md](README.md).*
