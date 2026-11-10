# Audit Kode Mati — QR-SAR

**Tanggal:** 10 November 2026
**Lingkup:** seluruh repo, khusus kode yang tidak berfungsi lagi akibat pembersihan atau penyederhanaan fitur, tampilan, dan logika.
**Sifat:** analisis statis, baca saja. Tidak ada kode yang dijalankan atau diubah.

## Apa repo ini

QR-SAR adalah aplikasi web PHP + MySQL untuk Kantor SAR Kendari: laporan operasi SAR, RENOPS, BRA, naskah WA, dan peta GIS. Beban yang diasumsikan kecil: beberapa petugas memakai bersamaan, satu server.

## Cara cek

- Setiap fungsi, endpoint, file, dan kelas CSS dicari di seluruh PHP, JS, dan view.
- Pencarian mencakup panggilan lewat teks (`onclick`, `'nama'`) dan panggilan dinamis.
- Tidak ada panggilan dinamis, kecuali `hydrateRow`, dan itu masih dipakai.
- Semua temuan di bawah tidak punya pemanggil di aplikasi.
- Kalau ada tes yang ikut terdampak, disebutkan di temuannya.

## Ringkasan

| No | Temuan | Prioritas | Perkiraan baris |
|----|--------|-----------|-----------------|
| 1 | Pembuat naskah BRA versi PHP lama | Tinggi | ±250 |
| 2 | Endpoint API admin dan kata sandi tak terpakai | Tinggi | ±45 |
| 3 | AI draft LAPBANG | Sedang | ±75 |
| 4 | Template BRA Pergerakan Kapal di server | Sedang | ±160 |
| 5 | Rute OSRM lewat server | Sedang | ±110 |
| 6 | Endpoint CRUD/statistik duplikat halaman | Sedang | ±200 |
| 7 | CSS tampilan lama sebelum Tailwind | Sedang | ±450 |
| 8 | Fungsi tombol preset/AI yang tombolnya sudah dihapus | Sedang | ±20 |
| 9 | Fungsi JS lama tanpa pemanggil | Sedang | ±230 |
| 10 | Fungsi PHP alias/delegasi sisa refactor | Rendah | ±120 |
| 11 | View error 403 tidak dipakai | Rendah | 1 file |
| 12 | Komentar yatim | Rendah | 3 |
| 13 | Route GIS alias versi tunggal | Rendah | kecil |

---

## Prioritas Tinggi

### 1. Pembuat naskah BRA versi PHP lama

**Lokasi:** `helpers/BraHelper.php:517` sampai akhir file (±250 baris)

- **Apa ini:** `buildSectionsFromLaporanAwal`, `buildSectionsFromRenops`, `buildSectionsFromSitrep`, `buildSectionsFromPenutupan`, `getDefaultTemplate`, `getSectionCode`.
- **Masalah:** sudah digantikan `assets/js/bra/bra_templates.js`. Isinya juga data contoh tetap ("6 ORANG") dan "0 ORANG", yang bertentangan dengan aturan baru "-".
- **Perbaikan:** hapus keenam fungsi itu. `buildSectionsFromMapel` dan `countBraWords` tetap, karena masih dipakai.
- **Tes terdampak:**
  - `tests/test_audit_optimalisasi_menyeluruh.php:223`
  - `tests/test_tahap2_logic_integrity.php:142-156`
  - `tests/test_bra_mapel.php:175-180`
- **Kalau dibiarkan:** ada yang menyambungkannya lagi dan BRA keluar dengan angka palsu.

### 2. Endpoint API admin dan kata sandi yang tidak dipanggil UI

**Lokasi:**
- `api/controllers/AuthApiController.php:41` (`/auth/change-password`)
- `api/controllers/AuthApiController.php:57` (`/auth/reset-default`)
- `api/controllers/SettingsApiController.php:49` (`/export/sql`)
- `api/controllers/SettingsApiController.php:62` (`/database/export`, `/backup/export`)
- `models/SettingsModel.php:365` (`resetToDefaultPassword`)
- `models/SettingsModel.php:397` (`exportJsonBackup`)

- **Apa ini:** lima route API beserta dua fungsi model yang hanya dipakai route itu.
- **Masalah:** UI memakai aksi di `admin.php`. `/auth/reset-default` mengembalikan sandi ke nilai bawaan yang sudah diketahui umum ("Kendari304"). Endpoint ini dilindungi login admin dan CSRF, tapi tetap jalur tambahan yang tidak perlu ada.
- **Perbaikan:** hapus kelima route dan dua fungsi model itu.
- **Tes terdampak:** `tests/test_http_security.php:173` memakai `/export/sql`; ganti ke endpoint admin lain.
- **Kalau dibiarkan:** ada jalur reset sandi dan unduh database yang tidak dipakai dan tidak diawasi.

---

## Prioritas Sedang

### 3. AI draft LAPBANG

**Lokasi:** `api/controllers/AiApiController.php:68`, `helpers/AiHelper.php:347` (±75 baris)

- **Apa ini:** `/ai/draft-sitrep` dan `AiHelper::draftSitrep`.
- **Masalah:** form LAPBANG hanya memanggil `/ai/refine-sitrep`.
- **Perbaikan:** hapus route dan fungsinya.
- **Kalau dibiarkan:** sekitar 75 baris tidak terpakai ikut dirawat.

### 4. Template BRA Pergerakan Kapal di server

**Lokasi:** `api/controllers/BraApiController.php:51` dan `:58`, `helpers/BraHelper.php:216-370` (±160 baris)

- **Apa ini:** `/bra/vessels`, `/bra/vessel-template`, `getAvailableVessels`, `buildSectionsFromVesselMovement`.
- **Masalah:** kategori PERGERAKAN_KAPAL masih ada di UI, tapi form tidak memanggil endpoint ini.
- **Perbaikan:** hapus. Atau, kalau isi otomatis data kapal masih diinginkan, sambungkan ke form.
- **Tes terdampak:** `tests/test_tahap3_bra_fixes.php:113-170`.
- **Kalau dibiarkan:** data kapal (nakhoda, POB) bisa basi tanpa ada yang sadar.

### 5. Rute OSRM lewat server

**Lokasi:** `api/controllers/GisApiController.php:289`, `:611`, `:673` (±110 baris)

- **Apa ini:** `/gis/directions` dan `/maps/calculate-route`, beserta `handleDirections` dan `handleCalculateRoute`.
- **Masalah:** tidak ada JS yang memanggil keduanya.
- **Perbaikan:** hapus route dan dua fungsi penanganannya.
- **Kalau dibiarkan:** ada jalur permintaan keluar ke layanan rute yang tidak dipakai.

### 6. Endpoint CRUD dan statistik yang sudah ditangani halaman biasa

**Lokasi:** (±200 baris)
- `api/controllers/OperasiApiController.php:12` (`/laporan/stats`, `/monitoring/stats`)
- `api/controllers/OperasiApiController.php:64` (`POST /operasi`)
- `api/controllers/OperasiApiController.php:81` (`/operasi/{id}/close`)
- `api/controllers/RenopsApiController.php:12` (`POST /operasi/{id}/renops`)
- `api/controllers/SitrepApiController.php:12` (`POST /operasi/{id}/laporan`)
- `api/controllers/BraApiController.php:21` (`/bra/next-number`)
- `api/controllers/MasterApiController.php:12-125` (`/master/alut*`, `/master/tim*`, `/sar-units`, `/settings/pejabat*`)
- `api/controllers/GisApiController.php:65` (alias `/gis/data`)

- **Apa ini:** endpoint yang pekerjaannya sudah dijalankan UI lewat `operasi.php`, `master.php`, dan form halaman.
- **Masalah:** tidak ada pemanggil dari UI.
- **Perbaikan:** hapus. **Pastikan dulu tidak ada aplikasi luar (mobile atau integrasi) yang memakai API ini.**
- **Tes terdampak:** `tests/test_audit_modul_6_gis_plotting.php:399-405`; ganti ke `/gis/bundle`.
- **Kalau dibiarkan:** ada dua jalur untuk aturan yang sama, dan nanti bisa berbeda perilaku.

### 7. CSS tampilan lama sebelum Tailwind

**Lokasi:** `assets/css/style.css:970-1170` dan blok lain (±66 aturan, ±450 baris)

- **Apa ini:**
  - kertas BRA lama: `.official-bra-paper`, `.bra-kop-box`, `.bra-kop-text`, `.bra-main-grid`, `.bra-grid-header`, `.bra-grid-upper`, `.bra-upper-left`, `.bra-upper-right`, `.bra-body-area`, `.bra-section-item`, `.bra-sec-code`, `.bra-sec-text`, `.bra-twb-line`, `.bra-footer-grid`, `.bra-footer-left`, `.bra-footer-right`, `.senkom-four-cols`, `.senkom-four-cells`;
  - komponen lama: `.kpi-*`, `.nav-pill-*`, `.badge-*`, `.sidebar-header`, `.sidebar-nav`, `.sidebar-footer`, `.nav-item`, `.nav-label`, `.nav-icon-box`, `.nav-sub`, `.nav-title`, `.card-header`, `.card-title`;
  - lain-lain: `.modal-lg`, `.modal-xl`, `.table-sar`, `.form-label`, `.pulse-dot`, `.btn-success`, `#gisMapCanvas`.
- **Masalah:** tidak ada elemen yang memakainya. Kelas ini juga tidak dirakit secara dinamis. Satu-satunya kelas dinamis adalah `route-*`, dan itu tidak termasuk daftar ini.
- **Perbaikan:** hapus blok-blok tersebut.
- **Kalau dibiarkan:** CSS yang diunduh setiap halaman lebih besar dari perlu.

### 8. Fungsi tombol preset dan AI yang tombolnya sudah dihapus

**Lokasi:** (±20 baris)
- `assets/js/pages/operasi_form.js:19` (`setEtaPreset`)
- `assets/js/pages/operasi_form.js:23` (`insertKronologiPreset`)
- `assets/js/pages/operasi_form.js:1887` (`setAiDraftInput`)
- `assets/js/pages/sitrep_form.js:178` (`setAiSitrepDraftInput`)

- **Masalah:** tidak ada `onclick` atau teks lain yang memanggilnya.
- **Perbaikan:** hapus keempat fungsi.
- **Kalau dibiarkan:** sekitar 20 baris tanpa fungsi.

### 9. Fungsi JS lama tanpa pemanggil

**Lokasi:** (±230 baris)
- `assets/js/bra.js:153-175`: `setPaperScale` dan `setPaperSize` (elemen `#scale-label` tidak ada di view)
- `assets/js/gis.js:312-375`: `loadSarUnits` dan `drawSearchPattern`
- `assets/js/gis_master.js:666`: `getVerifiedMaritimePresets`
- `assets/js/app.js:233`, `:444`, `:448`: `updateThemeIcon`, `showConfirmDialog`, `showSuccessAlert`
- `assets/js/utils/common.js:209`: `countWords`
- `assets/js/pages/renops_form.js:961`: `formatDmsCoordPart`
- `assets/js/gis/geodesy.js:170`, `:306`: `calculateDestinationPoint`, `calculateCentroid`
- `assets/js/gis/patterns.js:494-522`: `calculatePatternDistance`, `calculateAreaSwept`
- `assets/js/gis_master.js:6741-6750`: anggota `SAR_GIS` `setMeasureMode`, `setSearchAreaShape`, `setUnit`, `getUnit`, `updateBadges`. Fungsi di dalamnya tetap; yang dihapus hanya baris ekspornya.

- **Masalah:** tidak dipanggil di mana pun, dan tidak ada tes yang memakainya.
- **Perbaikan:** hapus.
- **Kalau dibiarkan:** pembaca mengira fitur itu masih aktif.

---

## Prioritas Rendah

### 10. Fungsi PHP alias/delegasi sisa refactor

**Lokasi:** (±120 baris)
- `models/SettingsModel.php:51-77`, `:404-410`: `updateSatker`, `updatePassword`, `savePejabat`, `updatePejabat`, `deletePejabat`, `getDbStats`, `restoreFromJson`, `splitSqlStatements`, `restoreFromSQL`
- `models/MasterModel.php:87-101`: `createSarUnit`, `updateSarUnit`, `deleteSarUnit`, `setDefaultSarUnit`
- `models/SarUnitModel.php:29`, `:36`: `getSarUnits`, `getById`
- `models/PejabatModel.php:21`, `:28`: `getPejabat`, `getById`
- `models/UserModel.php:150`: `updatePassword`
- `helpers/AuthHelper.php:15`, `:477`, `:485`: `clearCache`, `getCsrfToken`, `verifyCsrf`
- `config/app.php:309`: `verifyCsrfToken()`
- `helpers/TemplateHelper.php:37`, `:101`: `getNamaHariIndo`, `formatDMS`
- `helpers/ViewHelper.php:28`: `resolve`
- `models/OperasiModel.php:96`: `countAll`
- `services/LogService.php:118`, `:170`, `:192`: `debug`, `getLogContent`, `cleanOldLogs`
- `models/GisModel.php:883`: `exportGeoJson`
- `api/controllers/RenopsDokumenApiController.php:150-156`: `uploadFoto`, `jsonError`

- **Masalah:** tidak ada pemanggil di aplikasi. Satu hal lebih penting dari sekadar kode mati: `cleanOldLogs` tidak pernah dijalankan, jadi file log harian di `storage/logs` terus menumpuk.
- **Perbaikan:** hapus alias-aliasnya. Untuk `cleanOldLogs`, pilih: dijadwalkan atau dihapus.
- **Tes terdampak:**
  - `tests/test_phase4_refactoring_architecture.php:68`
  - `tests/test_audit_modul_2_input_create.php:374-392`
  - `tests/test_phase6_decoupled_services.php:164`
  - `tests/test_phase7_migrations_cleanup.php:61`
  - `tests/test_audit_modul_6_gis_plotting.php:323`
  - `tests/test_phase8_relations_logger.php:225`
- **Kalau dibiarkan:** ada dua nama untuk satu fungsi, dan log bisa memenuhi disk.

### 11. View error 403 tidak dipakai

**Lokasi:** `views/errors/403.php`

- **Masalah:** Router dan `.htaccess` mengirim teks polos untuk 403, bukan halaman ini. README juga menyebut `404.php`, padahal file itu tidak ada.
- **Perbaikan:** hapus file 403.php dan perbaiki baris README.
- **Kalau dibiarkan:** file menyesatkan pembaca.

### 12. Komentar yatim

**Lokasi:** `services/WhatsAppTemplateService.php:85-87`

- **Apa ini:** docblock "Laporan Awal" tanpa fungsi di bawahnya.
- **Masalah:** komentar itu nempel ke `formatJarakTempuh`, jadi salah keterangan.
- **Perbaikan:** hapus tiga baris itu.
- **Kalau dibiarkan:** dokumentasi kode salah.

### 13. Route GIS alias versi tunggal

**Lokasi:** `api/controllers/GisApiController.php` (`/gis/data`, `/gis/point`, `/gis/polygon`, `/gis/pattern`, `/gis/route`)

- **Apa ini:** route alias di samping versi jamak yang dipakai UI (`/gis/points` dan seterusnya).
- **Masalah:** dua nama untuk satu jalur.
- **Perbaikan:** sederhanakan pola regex ke versi jamak saja. Kerjakan bersama no. 6.
- **Kalau dibiarkan:** tidak ada yang rusak.

---

## Kesimpulan

- **Verdict:** tidak ada bug fungsional. Prioritas pertama: no. 2 (jalur reset sandi dan unduh database yang tidak terpakai), lalu no. 1.
- **Aman dihapus langsung:** no. 7–9.
- **Perlu kepastian dulu:** no. 3–6 dan 13, yaitu bahwa tidak ada aplikasi luar yang memakai API.
- **Lean:** sekitar −1.800 baris mungkin, 0 dependensi.

## Tidak dicek

- Kolom atau tabel database yang tidak terpakai. Menghapusnya berisiko kehilangan data, jadi tidak disarankan.
- Isi `assets/css/theme-minimal.css`; sebagian besar menimpa kelas vendor.
- Tes yang hanya menguji kode mati, selain yang disebut di atas.
- Folder `docs/` dan `.superpowers/`.
