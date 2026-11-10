# Audit Kode Mati Lanjutan (Ponytail Audit) — QR-SAR

**Tanggal:** 10 November 2026  
**Lingkup:** Seluruh basis kode produksi (`controllers/`, `api/`, `models/`, `services/`, `helpers/`, `views/`, `assets/`, `config/`) diverifikasi silang terhadap **81 suite pengujian PHP** dan **6 suite pengujian JS**.  
**Sifat:** Laporan audit statis (baca saja) — tidak mengubah kode apa pun.

## Apa repo ini

QR-SAR adalah sistem web internal Kantor SAR (Basarnas) Kendari berbasis PHP 8 + MySQL dan JavaScript modular untuk pengelolaan Operasi SAR, RENOPS (SMEAC), Berita Radio (BRA), LAPBANG (SITREP), verifikasi dokumen QR, serta pemetaan taktis GIS. Beban kerja yang diasumsikan: belasan petugas operator dan pimpinan bekerja bersamaan dalam satu server.

Seluruh temuan di bawah dipastikan memiliki **0 pemanggil pada alur kerja aplikasi** serta dipetakan dampaknya terhadap suite pengujian otomatis agar pembersihan tidak memicu eror atau regresi.

---

## Ringkasan Prioritas

| No | Temuan | Prioritas | Dampak ke Tes / Sistem | Perkiraan Baris |
|----|--------|-----------|------------------------|-----------------|
| 1 | Asersi yatim `handleRoutingDirectionsRoute` & 3 fungsi `GeoHelper` | **Tinggi** (*Must Fix*) | Menyelaraskan 1 test suite yang gagal | ±24 baris |
| 2 | Pembuat naskah BRA versi PHP lama (berisi data contoh palsu) | **Tinggi** (*Must Fix*) | Bebas tes (2 fungsi) / 3 tes (6 fungsi) | ±265 baris |
| 3 | Pembersih log harian `LogService::cleanOldLogs()` mati | **Tinggi** (*Must Fix*) | Tidak ada (log berisiko memenuhi disk) | ±15 baris |
| 4 | 9 fungsi JavaScript warisan tanpa pemanggil | **Menengah** (*Should Fix*) | 0 tes terdampak (100% aman) | ±165 baris |
| 5 | 53 aturan CSS tampilan lama pra-Tailwind | **Menengah** (*Should Fix*) | 0 tes terdampak (100% aman) | ±420 baris |
| 6 | Template BRA Pergerakan Kapal di server tidak tersambung ke UI | **Menengah** (*Should Fix*) | 1 blok tes di `test_tahap3_bra_fixes.php` | ±175 baris |
| 7 | 10 metode pembungkus (*wrapper/alias*) PHP tanpa pemanggil | **Rendah** (*Nice to Have*) | 0 tes terdampak (100% aman) | ±45 baris |
| 8 | View `views/errors/403.php` belum dihubungkan ke respon penolakan | **Rendah** (*Nice to Have*) | 0 tes terdampak | 1 berkas |

---

## 🔴 Prioritas Tinggi (*Must Fix* — Risiko Regresi Tes, Data Palsu, & Penumpukan Disk)

### 1. Asersi yatim `handleRoutingDirectionsRoute` & 3 fungsi `GeoHelper` tak bertuan
- **Lokasi:**
  - `tests/test_phase8_relations_logger.php:176-179`
  - `helpers/GeoHelper.php:90-109` (`calculateDistanceNm`, `calculateDistanceKm`, `calculateBearing`)
- **Apa ini:** Pemeriksaan string statis `handleRoutingDirectionsRoute` di berkas uji Fase 8 serta tiga metode hitung jarak/haluan di `GeoHelper`.
- **Masalah:** Setelah rute OSRM (`handleRoutingDirectionsRoute`, `handleDirections`, `handleCalculateRoute`) dibersihkan dari `api/controllers/GisApiController.php`, ketiga metode di `GeoHelper` menjadi kode yatim (0 pemanggil di produksi dan 0 di tes), sedangkan asersi lama di `test_phase8_relations_logger.php` menyebabkan kegagalan pada *test runner*.
- **Perbaikan:** Hapus asersi `handleRoutingDirectionsRoute` di `tests/test_phase8_relations_logger.php:176-179` dan hapus ketiga metode yatim di `helpers/GeoHelper.php:90-109`.
- **Jika dibiarkan:** Suite pengujian otomatis `test_phase8_relations_logger.php` gagal (FAIL) dan `GeoHelper` menyimpan fungsi mati.

### 2. Pembuat naskah BRA versi PHP lama dengan angka contoh palsu ("6 ORANG")
- **Lokasi:** `helpers/BraHelper.php:517-784`
  - Bebas dampak tes: `getSectionCode` (`:557-560`), `buildSectionsFromRenops` (`:623-677`)
  - Terikat tes warisan: `getDefaultTemplate` (`:517-536`), `buildSectionsFromLaporanAwal` (`:562-621`), `buildSectionsFromSitrep` (`:679-736`), `buildSectionsFromPenutupan` (`:738-784`)
- **Apa ini:** Enam metode penyusun naskah BRA sisi server sebelum migrasi ke sisi klien.
- **Masalah:** Penyusunan draf BRA kini sepenuhnya ditangani oleh `assets/js/bra/bra_templates.js`. Fungsi PHP lama ini tidak lagi dipakai fitur aplikasi, namun masih menyimpan nilai bawaan berbahaya (*hardcoded*) seperti `* TIM RESCUE KANSAR KENDARI : 6 ORANG` dan `1 UNIT RESCUE CAR` yang melanggar standar aturan kosong (`-`).
- **Perbaikan:** Hapus `getSectionCode()` dan `buildSectionsFromRenops()` secara langsung, atau hapus keenam metode tersebut sekaligus bersama blok pengujian warisannya di:
  - `tests/test_audit_optimalisasi_menyeluruh.php:223`
  - `tests/test_tahap2_logic_integrity.php:142-156`
  - `tests/test_bra_mapel.php:175-180`
  *(Pertahankan `countBraWords()` dan `buildSectionsFromMapel()` karena masih aktif dipakai).*
- **Jika dibiarkan:** Berisiko menghasilkan naskah BRA berisi jumlah personil dan alut palsu apabila dipanggil kembali oleh pengembang.

### 3. Fungsi pembersih log harian `LogService::cleanOldLogs()` mati / tidak pernah dijalankan
- **Lokasi:** `services/LogService.php:192-206`
- **Apa ini:** Metode untuk menghapus berkas log harian `storage/logs/qrsar-YYYY-MM-DD.log` yang lebih tua dari 30 hari.
- **Masalah:** Metode ini sudah ditulis lengkap tetapi memiliki **0 pemanggil** di seluruh aplikasi, sehingga pembersihan otomatis tidak pernah berjalan.
- **Perbaikan:** Panggil `LogService::cleanOldLogs(30)` secara berkala/probabilistik ringan saat penulisan log atau saat admin membuka tab log di `AdminController.php`, atau hapus jika rotasi diserahkan ke cron OS.
- **Jika dibiarkan:** Berkas log harian terus menumpuk tanpa batas di disk server.

---

## 🟡 Prioritas Menengah (*Should Fix* — Kode Mati Murni Tanpa Dampak Tes)

### 4. 9 fungsi JavaScript warisan yang sudah tidak memiliki pemanggil (±165 baris)
- **Lokasi:**
  - `assets/js/app.js:234-236` (`updateThemeIcon`)
  - `assets/js/gis_master.js:666-692` (`getVerifiedMaritimePresets`)
  - `assets/js/pages/renops_form.js:962-964` (`formatDmsCoordPart`)
  - `assets/js/gis.js:312-345` (`loadSarUnits`) dan `:348-376` (`drawSearchPattern`)
  - `assets/js/utils/common.js:209-215` (`countWords`)
  - `assets/js/gis/geodesy.js:170-191` (`calculateDestinationPoint`) dan `:306-317` (`calculateCentroid`)
  - `assets/js/gis/patterns.js:494-522` (`calculatePatternDistance`, `calculateAreaSwept` beserta baris ekspornya di `:545-546`)
- **Masalah:** Fungsi-fungsi sisa pra-refaktor modul `QrSarUtils` dan `gis_master.js` ini memiliki **0 pemanggil di produksi** maupun **0 referensi di seluruh tes**.  
  *(Catatan penting: `setUnit`, `getUnit`, dan `updateBadges` pada `gis_master.js` **jangan dihapus** karena masih dipakai oleh `renops_form.js` dan diuji pada `test_gis_dynamic_units_and_badges.php`).*
- **Perbaikan:** Hapus ke-9 fungsi beserta baris ekspornya di `patterns.js`.
- **Jika dibiarkan:** Membebani ukuran bundel JS yang diunduh browser dan menyesatkan pembaca kode.

### 5. 53 aturan CSS tampilan lama pra-Tailwind yang tidak dipakai elemen mana pun (±420 baris)
- **Lokasi:** `assets/css/style.css:355-1176`
  - Navigasi & KPI lama (`L355–638`): `.sidebar-header`, `.nav-label`, `.nav-icon-box`, `.nav-title`, `.nav-sub`, `.nav-pill`, `.nav-pill-orange`, `.nav-pill-teal`, `.nav-pill-emerald`, `.nav-pill-amber`, `.kpi-grid`, `.kpi-card`, `.kpi-success`, `.kpi-warning`, `.kpi-danger`, `.kpi-info`, `.kpi-val`, `.kpi-label`, `.kpi-icon`, `.badge-open`, `.badge-closed`, `.badge-primary`, `.badge-info`, `.pulse-dot`.
  - Komponen tabel/modal lama (`L701–942`): `.btn-success`, `.form-label`, `.table-sar`, `.modal-lg`, `.modal-xl`.
  - Grid kertas BRA lama (`L974–1176`): `.official-bra-paper`, `.bra-kop-box`, `.bra-kop-text`, `.bra-main-grid`, `.bra-grid-header`, `.bra-grid-upper`, `.bra-upper-left`, `.bra-upper-right`, `.bra-body-area`, `.bra-section-item`, `.bra-sec-code`, `.bra-sec-text`, `.bra-twb-line`, `.bra-footer-grid`, `.bra-footer-left`, `.bra-footer-right`, `.senkom-four-cols`, `.senkom-four-cells`, `#gisMapCanvas`.
- **Masalah:** Seluruh tampilan telah bermigrasi ke kelas utilitas Tailwind CSS. Ke-53 selektor tersebut memiliki **0 pemakaian di seluruh view/JS** dan **0 pemeriksaan di seluruh tes**.  
  *(Catatan penting: `.bra-table-wrapper`, `.senkom-vertical-label`, `.bra-section-content`, `.bra-text-line`, dan aturan `@media print` **wajib dipertahankan** karena aktif dipakai saat cetak BRA dan diverifikasi oleh `test_bra_perfection.php` & `test_bra_mapel.php`).*
- **Perbaikan:** Hapus ke-53 blok aturan CSS yatim di atas secara selektif tanpa menyentuh aturan cetak BRA.
- **Jika dibiarkan:** Browser tetap mengunduh ratusan baris CSS mati di setiap halaman.

### 6. Template BRA Pergerakan Kapal di server yang tidak tersambung ke form UI (±175 baris)
- **Lokasi:**
  - `api/controllers/BraApiController.php:50-64` (`/bra/vessels`, `/bra/vessel-template`)
  - `helpers/BraHelper.php:216-375` (`getAvailableVessels`, `buildSectionsFromVesselMovement`)
- **Apa ini:** Endpoint dan pembentuk template otomatis pergerakan kapal SAR berbasis data statis.
- **Masalah:** Form pembuatan BRA di UI tidak pernah memanggil kedua endpoint ini ketika kategori `PERGERAKAN_KAPAL` dipilih; data nakhoda serta POB di dalamnya juga bersifat statis (*hardcoded*). Satu-satunya pemanggil hanyalah `tests/test_tahap3_bra_fixes.php:113-170`.
- **Perbaikan:** Hapus kedua route di `BraApiController.php`, hapus kedua fungsi di `BraHelper.php`, serta hapus blok pengujiannya di `tests/test_tahap3_bra_fixes.php:113-170`.
- **Jika dibiarkan:** Menyimpan data kapal statis yang berpotensi basi tanpa dipakai alur kerja UI.

---

## 🟢 Prioritas Rendah (*Nice to Have* — Wrapper & Alias Sisa Refactoring Tanpa Tes)

### 7. 10 metode pembungkus (*wrapper/alias*) PHP dengan 0 pemanggil di produksi & 0 di tes (±45 baris)
- **Lokasi:**
  - `api/controllers/RenopsDokumenApiController.php:156-162` (`uploadFoto`, `jsonError`)
  - `config/app.php:310-312` (`verifyCsrfToken`)
  - `helpers/AuthHelper.php:478-480` (`getCsrfToken`) dan `:486-488` (`verifyCsrf`)
  - `models/MasterModel.php:92-94` (`updateSarUnit`) dan `:100-102` (`setDefaultSarUnit`)
  - `models/SettingsModel.php:52-54` (`updateSatker`) dan `:60-62` (`savePejabat`)
  - `services/LogService.php:119-121` (`debug`)
- **Masalah:** Sepuluh fungsi satu-baris ini adalah alias sisa refactoring yang tidak pernah dipanggil di kode produksi maupun di 81 file pengujian.  
  *(Catatan: metode facade lain seperti `MasterModel::createSarUnit`, `MasterModel::deleteSarUnit`, `SettingsModel::getDbStats`, `SettingsModel::exportJsonBackup`, `GisModel::exportGeoJson`, dan `LogService::getLogContent` **wajib dipertahankan** karena diuji langsung oleh suite pengujian regresi Fase 4, 6, dan 8).*
- **Perbaikan:** Hapus ke-10 metode alias yang terbukti tidak digunakan di aplikasi maupun tes.
- **Jika dibiarkan:** Terdapat dua nama fungsi untuk satu tugas yang sama.

### 8. Halaman `views/errors/403.php` belum dihubungkan ke respon penolakan akses
- **Lokasi:** `views/errors/403.php`
- **Apa ini:** Berkas tampilan error 403 resmi berdesain Basarnas.
- **Masalah:** Saat ini akses ditolak oleh `helpers/AuthHelper.php` dan `helpers/Router.php` hanya mengembalikan teks polos, sehingga template HTML ini menganggur.
- **Perbaikan:** Hubungkan `include VIEWS_DIR . '/errors/403.php'` pada respon penolakan HTTP non-API, atau hapus berkasnya jika hanya ingin memakai teks polos.
- **Jika dibiarkan:** Berkas tampilan tetap tersimpan tanpa pernah di-*render*.

---

## Kesimpulan & Catatan Audit

- **Verdict:** Secara fungsional sistem sehat; prioritas pertama adalah **Temuan #1** (sinkronisasi asersi `test_phase8_relations_logger.php` dan pembersihan 3 fungsi `GeoHelper`) agar **81/81 suite pengujian PHP** kembali lulus 100%, disusul **Temuan #2, #4, #5, dan #7** yang aman dibersihkan tanpa merusak alur kerja.
- **Lean:** Potensi pengurangan **±1.050 baris kode mati** (`0` perubahan dependensi).
- **Tidak dicek / sengaja dipertahankan:** Skema kolom tabel MySQL lama (demi menjaga data historis operasi), `assets/css/theme-minimal.css` (penimpaan gaya vendor), serta endpoint API di `OperasiApiController` dan `GisApiController` yang masih dikonsumsi oleh dashboard dan suite pengujian E2E.

