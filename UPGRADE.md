# Upgrading

## From 1.x to 2.0

### Region codes changed (breaking)

The data bundled with 1.x mixed **BPS** codes into what was labelled Kepmendagri data. 2.0 ships the
official **Kepmendagri No 300.2.2-2138 Tahun 2025** dataset (38 provinces, 514 regencies, 7,285
districts, 83,762 villages). If your application **stores region codes** (e.g. an `addresses.regency_code`
column), review them before upgrading:

- **Provinces:** the Papua provinces were split in 2022. Old `91` (Papua Barat) is now `92` Papua Barat or
  `96` Papua Barat Daya; old `94` (Papua) is now `91` Papua, `93` Papua Selatan, `94` Papua Tengah or
  `95` Papua Pegunungan. All other province codes are unchanged.
- **Regencies:** 199 of 514 codes changed, and **164 old codes now belong to a different regency**
  (e.g. `31.71` was Jakarta Selatan and is now Jakarta Pusat). Map stored codes with the table below.
- **Districts:** 1.x district codes were truncated 7-digit BPS codes; 1,520 of them were shared by two or
  more districts (e.g. Rancasari and Gedebage were both `32.73.10`), so a stored district code cannot be
  mapped reliably. Re-resolve districts from the stored regency + district name, or ask users to re-select.
- **Villages:** 1.x only shipped 6 sample villages, so no stored village codes are expected.

Look up a code in the new data with `Nusantara::find($code)` before and after migrating.

### Names follow Kepmendagri spelling

For example `DKI JAKARTA` is now `DAERAH KHUSUS IBUKOTA JAKARTA`, `DI YOGYAKARTA` is
`DAERAH ISTIMEWA YOGYAKARTA` and `KOTA JAKARTA SELATAN` is `KOTA ADMINISTRASI JAKARTA SELATAN`.
The old names remain search aliases, so `Nusantara::search('DKI Jakarta')` and
`Nusantara::regencies('DKI JAKARTA')` keep working. Code that compares names with `===` must be updated.

### Database driver

Re-seed after upgrading so the tables hold the new codes:

```bash
php artisan nusantara:seed
```

Seeding upserts by code, so rows whose old code no longer exists remain. To start clean, truncate the four
`nusantara_*` tables (villages, districts, regencies, provinces, in that order) before seeding.

### Migration is only loaded for the database driver

The `create_nusantara_tables` migration is now registered only when `NUSANTARA_DRIVER=database`.

- **Database driver:** nothing to do.
- **File driver:** new installs no longer get the four `nusantara_*` tables. If an earlier `migrate` already
  created them, they are unused — drop them and delete the `create_nusantara_tables` row from the
  `migrations` table, or leave them.
- `nusantara:install --migrate` now fails unless the driver is `database`, and runs only the Nusantara
  migration instead of every pending app migration.

### Other changes

- `villages.php` now stores one `"<code>\t<NAME>\t<postal_code>"` line per village instead of an array of rows
  (about 5 MB instead of 60 MB in memory). A custom `data_path` with the old array format is still read.
- `Nusantara` is bound as `scoped` instead of `singleton`.
- Use `Nusantara\Facades\Nusantara`; `Nusantara\NusantaraFacade` still works but is deprecated.
- Database driver: postal-code results no longer embed nested relations inside `district` / `regency`.

### Regency code mapping (1.x → 2.0)

Only regencies whose code changed are listed.

| 1.x code | 1.x name | 2.0 code | 2.0 name |
|----------|----------|----------|----------|
| `11.01` | Kabupaten Simeulue | `11.09` | Kabupaten Simeulue |
| `11.02` | Kabupaten Aceh Singkil | `11.10` | Kabupaten Aceh Singkil |
| `11.03` | Kabupaten Aceh Selatan | `11.01` | Kabupaten Aceh Selatan |
| `11.04` | Kabupaten Aceh Tenggara | `11.02` | Kabupaten Aceh Tenggara |
| `11.05` | Kabupaten Aceh Timur | `11.03` | Kabupaten Aceh Timur |
| `11.06` | Kabupaten Aceh Tengah | `11.04` | Kabupaten Aceh Tengah |
| `11.07` | Kabupaten Aceh Barat | `11.05` | Kabupaten Aceh Barat |
| `11.08` | Kabupaten Aceh Besar | `11.06` | Kabupaten Aceh Besar |
| `11.09` | Kabupaten Pidie | `11.07` | Kabupaten Pidie |
| `11.10` | Kabupaten Bireuen | `11.11` | Kabupaten Bireuen |
| `11.11` | Kabupaten Aceh Utara | `11.08` | Kabupaten Aceh Utara |
| `11.14` | Kabupaten Aceh Tamiang | `11.16` | Kabupaten Aceh Tamiang |
| `11.16` | Kabupaten Aceh Jaya | `11.14` | Kabupaten Aceh Jaya |
| `11.73` | Kota Langsa | `11.74` | Kota Langsa |
| `11.74` | Kota Lhokseumawe | `11.73` | Kota Lhokseumawe |
| `12.01` | Kabupaten Nias | `12.04` | Kabupaten Nias |
| `12.02` | Kabupaten Mandailing Natal | `12.13` | Kabupaten Mandailing Natal |
| `12.04` | Kabupaten Tapanuli Tengah | `12.01` | Kabupaten Tapanuli Tengah |
| `12.05` | Kabupaten Tapanuli Utara | `12.02` | Kabupaten Tapanuli Utara |
| `12.06` | Kabupaten Toba Samosir | `12.12` | Kabupaten Toba |
| `12.07` | Kabupaten Labuhan Batu | `12.10` | Kabupaten Labuhanbatu |
| `12.08` | Kabupaten Asahan | `12.09` | Kabupaten Asahan |
| `12.09` | Kabupaten Simalungun | `12.08` | Kabupaten Simalungun |
| `12.10` | Kabupaten Dairi | `12.11` | Kabupaten Dairi |
| `12.11` | Kabupaten Karo | `12.06` | Kabupaten Karo |
| `12.12` | Kabupaten Deli Serdang | `12.07` | Kabupaten Deli Serdang |
| `12.13` | Kabupaten Langkat | `12.05` | Kabupaten Langkat |
| `12.15` | Kabupaten Humbang Hasundutan | `12.16` | Kabupaten Humbang Hasundutan |
| `12.16` | Kabupaten Pakpak Bharat | `12.15` | Kabupaten Pakpak Bharat |
| `12.71` | Kota Sibolga | `12.73` | Kota Sibolga |
| `12.72` | Kota Tanjung Balai | `12.74` | Kota Tanjungbalai |
| `12.73` | Kota Pematang Siantar | `12.72` | Kota Pematangsiantar |
| `12.74` | Kota Tebing Tinggi | `12.76` | Kota Tebing Tinggi |
| `12.75` | Kota Medan | `12.71` | Kota Medan |
| `12.76` | Kota Binjai | `12.75` | Kota Binjai |
| `13.01` | Kabupaten Kepulauan Mentawai | `13.09` | Kabupaten Kepulauan Mentawai |
| `13.02` | Kabupaten Pesisir Selatan | `13.01` | Kabupaten Pesisir Selatan |
| `13.03` | Kabupaten Solok | `13.02` | Kabupaten Solok |
| `13.04` | Kabupaten Sijunjung | `13.03` | Kabupaten Sijunjung |
| `13.05` | Kabupaten Tanah Datar | `13.04` | Kabupaten Tanah Datar |
| `13.06` | Kabupaten Padang Pariaman | `13.05` | Kabupaten Padang Pariaman |
| `13.07` | Kabupaten Agam | `13.06` | Kabupaten Agam |
| `13.08` | Kabupaten Lima Puluh Kota | `13.07` | Kabupaten Lima Puluh Kota |
| `13.09` | Kabupaten Pasaman | `13.08` | Kabupaten Pasaman |
| `13.10` | Kabupaten Solok Selatan | `13.11` | Kabupaten Solok Selatan |
| `13.11` | Kabupaten Dharmasraya | `13.10` | Kabupaten Dharmasraya |
| `14.01` | Kabupaten Kuantan Singingi | `14.09` | Kabupaten Kuantan Singingi |
| `14.03` | Kabupaten Indragiri Hilir | `14.04` | Kabupaten Indragiri Hilir |
| `14.04` | Kabupaten Pelalawan | `14.05` | Kabupaten Pelalawan |
| `14.05` | Kabupaten Siak | `14.08` | Kabupaten Siak |
| `14.06` | Kabupaten Kampar | `14.01` | Kabupaten Kampar |
| `14.07` | Kabupaten Rokan Hulu | `14.06` | Kabupaten Rokan Hulu |
| `14.08` | Kabupaten Bengkalis | `14.03` | Kabupaten Bengkalis |
| `14.09` | Kabupaten Rokan Hilir | `14.07` | Kabupaten Rokan Hilir |
| `14.73` | Kota Dumai | `14.72` | Kota Dumai |
| `15.06` | Kabupaten Tanjung Jabung Timur | `15.07` | Kabupaten Tanjung Jabung Timur |
| `15.07` | Kabupaten Tanjung Jabung Barat | `15.06` | Kabupaten Tanjung Jabung Barat |
| `15.08` | Kabupaten Tebo | `15.09` | Kabupaten Tebo |
| `15.09` | Kabupaten Bungo | `15.08` | Kabupaten Bungo |
| `16.08` | Kabupaten Ogan Komering Ulu Selatan | `16.09` | Kabupaten Ogan Komering Ulu Selatan |
| `16.09` | Kabupaten Ogan Komering Ulu Timur | `16.08` | Kabupaten Ogan Komering Ulu Timur |
| `16.72` | Kota Prabumulih | `16.74` | Kota Prabumulih |
| `16.73` | Kota Pagar Alam | `16.72` | Kota Pagar Alam |
| `16.74` | Kota Lubuk Linggau | `16.73` | Kota Lubuk Linggau |
| `18.01` | Kabupaten Lampung Barat | `18.04` | Kabupaten Lampung Barat |
| `18.02` | Kabupaten Tanggamus | `18.06` | Kabupaten Tanggamus |
| `18.03` | Kabupaten Lampung Selatan | `18.01` | Kabupaten Lampung Selatan |
| `18.04` | Kabupaten Lampung Timur | `18.07` | Kabupaten Lampung Timur |
| `18.05` | Kabupaten Lampung Tengah | `18.02` | Kabupaten Lampung Tengah |
| `18.06` | Kabupaten Lampung Utara | `18.03` | Kabupaten Lampung Utara |
| `18.07` | Kabupaten Way Kanan | `18.08` | Kabupaten Way Kanan |
| `18.08` | Kabupaten Tulangbawang | `18.05` | Kabupaten Tulang Bawang |
| `19.03` | Kabupaten Bangka Barat | `19.05` | Kabupaten Bangka Barat |
| `19.05` | Kabupaten Bangka Selatan | `19.03` | Kabupaten Bangka Selatan |
| `21.01` | Kabupaten Karimun | `21.02` | Kabupaten Karimun |
| `21.02` | Kabupaten Bintan | `21.01` | Kabupaten Bintan |
| `31.71` | Kota Jakarta Selatan | `31.74` | Kota Administrasi Jakarta Selatan |
| `31.72` | Kota Jakarta Timur | `31.75` | Kota Administrasi Jakarta Timur |
| `31.73` | Kota Jakarta Pusat | `31.71` | Kota Administrasi Jakarta Pusat |
| `31.74` | Kota Jakarta Barat | `31.73` | Kota Administrasi Jakarta Barat |
| `31.75` | Kota Jakarta Utara | `31.72` | Kota Administrasi Jakarta Utara |
| `53.01` | Kabupaten Sumba Barat | `53.12` | Kabupaten Sumba Barat |
| `53.02` | Kabupaten Sumba Timur | `53.11` | Kabupaten Sumba Timur |
| `53.03` | Kabupaten Kupang | `53.01` | Kabupaten Kupang |
| `53.04` | Kabupaten Timor Tengah Selatan | `53.02` | Kabupaten Timor Tengah Selatan |
| `53.05` | Kabupaten Timor Tengah Utara | `53.03` | Kabupaten Timor Tengah Utara |
| `53.06` | Kabupaten Belu | `53.04` | Kabupaten Belu |
| `53.07` | Kabupaten Alor | `53.05` | Kabupaten Alor |
| `53.08` | Kabupaten Lembata | `53.13` | Kabupaten Lembata |
| `53.09` | Kabupaten Flores Timur | `53.06` | Kabupaten Flores Timur |
| `53.10` | Kabupaten Sikka | `53.07` | Kabupaten Sikka |
| `53.11` | Kabupaten Ende | `53.08` | Kabupaten Ende |
| `53.12` | Kabupaten Ngada | `53.09` | Kabupaten Ngada |
| `53.13` | Kabupaten Manggarai | `53.10` | Kabupaten Manggarai |
| `53.16` | Kabupaten Sumba Tengah | `53.17` | Kabupaten Sumba Tengah |
| `53.17` | Kabupaten Sumba Barat Daya | `53.18` | Kabupaten Sumba Barat Daya |
| `53.18` | Kabupaten Nagekeo | `53.16` | Kabupaten Nagekeo |
| `61.02` | Kabupaten Bengkayang | `61.07` | Kabupaten Bengkayang |
| `61.03` | Kabupaten Landak | `61.08` | Kabupaten Landak |
| `61.04` | Kabupaten Mempawah | `61.02` | Kabupaten Mempawah |
| `61.05` | Kabupaten Sanggau | `61.03` | Kabupaten Sanggau |
| `61.06` | Kabupaten Ketapang | `61.04` | Kabupaten Ketapang |
| `61.07` | Kabupaten Sintang | `61.05` | Kabupaten Sintang |
| `61.08` | Kabupaten Kapuas Hulu | `61.06` | Kabupaten Kapuas Hulu |
| `62.06` | Kabupaten Sukamara | `62.08` | Kabupaten Sukamara |
| `62.07` | Kabupaten Lamandau | `62.09` | Kabupaten Lamandau |
| `62.08` | Kabupaten Seruyan | `62.07` | Kabupaten Seruyan |
| `62.09` | Kabupaten Katingan | `62.06` | Kabupaten Katingan |
| `62.10` | Kabupaten Pulang Pisau | `62.11` | Kabupaten Pulang Pisau |
| `62.11` | Kabupaten Gunung Mas | `62.10` | Kabupaten Gunung Mas |
| `62.12` | Kabupaten Barito Timur | `62.13` | Kabupaten Barito Timur |
| `62.13` | Kabupaten Murung Raya | `62.12` | Kabupaten Murung Raya |
| `64.02` | Kabupaten Kutai Barat | `64.07` | Kabupaten Kutai Barat |
| `64.03` | Kabupaten Kutai Kartanegara | `64.02` | Kabupaten Kutai Kartanegara |
| `64.04` | Kabupaten Kutai Timur | `64.08` | Kabupaten Kutai Timur |
| `64.05` | Kabupaten Berau | `64.03` | Kabupaten Berau |
| `65.01` | Kabupaten Malinau | `65.02` | Kabupaten Malinau |
| `65.02` | Kabupaten Bulungan | `65.01` | Kabupaten Bulungan |
| `65.03` | Kabupaten Tana Tidung | `65.04` | Kabupaten Tana Tidung |
| `65.04` | Kabupaten Nunukan | `65.03` | Kabupaten Nunukan |
| `71.07` | Kabupaten Bolaang Mongondow Utara | `71.08` | Kabupaten Bolaang Mongondow Utara |
| `71.08` | Kabupaten Siau Tagulandang Biaro | `71.09` | Kabupaten Kep. Siau Tagulandang Biaro |
| `71.09` | Kabupaten Minahasa Tenggara | `71.07` | Kabupaten Minahasa Tenggara |
| `71.10` | Kabupaten Bolaang Mongondow Selatan | `71.11` | Kabupaten Bolaang Mongondow Selatan |
| `71.11` | Kabupaten Bolaang Mongondow Timur | `71.10` | Kabupaten Bolaang Mongondow Timur |
| `72.01` | Kabupaten Banggai Kepulauan | `72.07` | Kabupaten Banggai Kepulauan |
| `72.02` | Kabupaten Banggai | `72.01` | Kabupaten Banggai |
| `72.03` | Kabupaten Morowali | `72.06` | Kabupaten Morowali |
| `72.04` | Kabupaten Poso | `72.02` | Kabupaten Poso |
| `72.05` | Kabupaten Donggala | `72.03` | Kabupaten Donggala |
| `72.06` | Kabupaten Toli-Toli | `72.04` | Kabupaten Toli-Toli |
| `72.07` | Kabupaten Buol | `72.05` | Kabupaten Buol |
| `73.08` | Kabupaten Maros | `73.09` | Kabupaten Maros |
| `73.09` | Kabupaten Pangkajene Dan Kepulauan | `73.10` | Kabupaten Pangkajene Dan Kepulauan |
| `73.10` | Kabupaten Barru | `73.11` | Kabupaten Barru |
| `73.11` | Kabupaten Bone | `73.08` | Kabupaten Bone |
| `73.25` | Kabupaten Luwu Timur | `73.24` | Kabupaten Luwu Timur |
| `74.01` | Kabupaten Buton | `74.04` | Kabupaten Buton |
| `74.02` | Kabupaten Muna | `74.03` | Kabupaten Muna |
| `74.03` | Kabupaten Konawe | `74.02` | Kabupaten Konawe |
| `74.04` | Kabupaten Kolaka | `74.01` | Kabupaten Kolaka |
| `74.09` | Kabupaten Buton Utara | `74.10` | Kabupaten Buton Utara |
| `74.10` | Kabupaten Konawe Utara | `74.09` | Kabupaten Konawe Utara |
| `75.01` | Kabupaten Boalemo | `75.02` | Kabupaten Boalemo |
| `75.02` | Kabupaten Gorontalo | `75.01` | Kabupaten Gorontalo |
| `75.03` | Kabupaten Pohuwato | `75.04` | Kabupaten Pohuwato |
| `75.04` | Kabupaten Bone Bolango | `75.03` | Kabupaten Bone Bolango |
| `76.01` | Kabupaten Majene | `76.05` | Kabupaten Majene |
| `76.02` | Kabupaten Polewali Mandar | `76.04` | Kabupaten Polewali Mandar |
| `76.04` | Kabupaten Mamuju | `76.02` | Kabupaten Mamuju |
| `76.05` | Kabupaten Mamuju Utara | `76.01` | Kabupaten Pasangkayu |
| `81.01` | Kabupaten Maluku Tenggara Barat | `81.03` | Kabupaten Kepulauan Tanimbar |
| `81.03` | Kabupaten Maluku Tengah | `81.01` | Kabupaten Maluku Tengah |
| `81.05` | Kabupaten Kepulauan Aru | `81.07` | Kabupaten Kepulauan Aru |
| `81.07` | Kabupaten Seram Bagian Timur | `81.05` | Kabupaten Seram Bagian Timur |
| `82.03` | Kabupaten Kepulauan Sula | `82.05` | Kabupaten Kepulauan Sula |
| `82.05` | Kabupaten Halmahera Utara | `82.03` | Kabupaten Halmahera Utara |
| `91.01` | Kabupaten Fak-Fak | `92.03` | Kabupaten Fak Fak |
| `91.02` | Kabupaten Kaimana | `92.08` | Kabupaten Kaimana |
| `91.03` | Kabupaten Teluk Wondama | `92.07` | Kabupaten Teluk Wondama |
| `91.04` | Kabupaten Teluk Bintuni | `92.06` | Kabupaten Teluk Bintuni |
| `91.05` | Kabupaten Manokwari | `92.02` | Kabupaten Manokwari |
| `91.06` | Kabupaten Sorong Selatan | `96.02` | Kabupaten Sorong Selatan |
| `91.07` | Kabupaten Sorong | `96.01` | Kabupaten Sorong |
| `91.08` | Kabupaten Raja Ampat | `96.03` | Kabupaten Raja Ampat |
| `91.09` | Kabupaten Tambrauw | `96.04` | Kabupaten Tambrauw |
| `91.10` | Kabupaten Maybrat | `96.05` | Kabupaten Maybrat |
| `91.11` | Kabupaten Manokwari Selatan | `92.11` | Kabupaten Manokwari Selatan |
| `91.12` | Kabupaten Pegunungan Arfak | `92.12` | Kabupaten Pegunungan Arfak |
| `91.71` | Kota Sorong | `96.71` | Kota Sorong |
| `94.01` | Kabupaten Merauke | `93.01` | Kabupaten Merauke |
| `94.02` | Kabupaten Jayawijaya | `95.01` | Kabupaten Jayawijaya |
| `94.03` | Kabupaten Jayapura | `91.03` | Kabupaten Jayapura |
| `94.04` | Kabupaten Nabire | `94.01` | Kabupaten Nabire |
| `94.08` | Kabupaten Kepulauan Yapen | `91.05` | Kabupaten Kepulauan Yapen |
| `94.09` | Kabupaten Biak Numfor | `91.06` | Kabupaten Biak Numfor |
| `94.10` | Kabupaten Paniai | `94.03` | Kabupaten Paniai |
| `94.11` | Kabupaten Puncak Jaya | `94.02` | Kabupaten Puncak Jaya |
| `94.12` | Kabupaten Mimika | `94.04` | Kabupaten Mimika |
| `94.13` | Kabupaten Boven Digoel | `93.02` | Kabupaten Boven Digoel |
| `94.14` | Kabupaten Mappi | `93.03` | Kabupaten Mappi |
| `94.15` | Kabupaten Asmat | `93.04` | Kabupaten Asmat |
| `94.16` | Kabupaten Yahukimo | `95.03` | Kabupaten Yahukimo |
| `94.17` | Kabupaten Pegunungan Bintang | `95.02` | Kabupaten Pegunungan Bintang |
| `94.18` | Kabupaten Tolikara | `95.04` | Kabupaten Tolikara |
| `94.19` | Kabupaten Sarmi | `91.10` | Kabupaten Sarmi |
| `94.20` | Kabupaten Keerom | `91.11` | Kabupaten Keerom |
| `94.26` | Kabupaten Waropen | `91.15` | Kabupaten Waropen |
| `94.27` | Kabupaten Supiori | `91.19` | Kabupaten Supiori |
| `94.28` | Kabupaten Mamberamo Raya | `91.20` | Kabupaten Mamberamo Raya |
| `94.29` | Kabupaten Nduga | `95.08` | Kabupaten Nduga |
| `94.30` | Kabupaten Lanny Jaya | `95.07` | Kabupaten Lanny Jaya |
| `94.31` | Kabupaten Mamberamo Tengah | `95.05` | Kabupaten Mamberamo Tengah |
| `94.32` | Kabupaten Yalimo | `95.06` | Kabupaten Yalimo |
| `94.33` | Kabupaten Puncak | `94.05` | Kabupaten Puncak |
| `94.34` | Kabupaten Dogiyai | `94.06` | Kabupaten Dogiyai |
| `94.35` | Kabupaten Intan Jaya | `94.07` | Kabupaten Intan Jaya |
| `94.36` | Kabupaten Deiyai | `94.08` | Kabupaten Deiyai |
| `94.71` | Kota Jayapura | `91.71` | Kota Jayapura |
