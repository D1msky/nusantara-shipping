# Data sources and licenses

The region data in this directory was generated with `php artisan nusantara:update-data` from:

| Data | Source | Basis |
|------|--------|-------|
| Codes and names of provinces, regencies, districts, villages | [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah) (`db/wilayah.sql`) | Kepmendagri No 300.2.2-2138 Tahun 2025 |
| Province and regency coordinates | [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah) (`db/wilayah_level_1_2.sql`) | |
| Village postal codes | [cahyadsn/wilayah_kodepos](https://github.com/cahyadsn/wilayah_kodepos) (`db/wilayah_kodepos.sql`) | Kepmendagri No 300.2.2-2138 Tahun 2025 |
| District coordinates | Carried over from this package's v1.x data, matched by regency + district name | Only kept when the point is unique and lies within the district's regency; 5,619 of 7,285 districts |

Counts: 38 provinces, 514 regencies, 7,285 districts, 83,762 villages (all with a postal code).

---

## cahyadsn/wilayah

MIT License

Copyright (c) 2017-2025 Cahya DSN

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## cahyadsn/wilayah_kodepos

MIT License

Copyright (c) 2024 Cahya DSN

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
