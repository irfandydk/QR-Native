# Dokumentasi Kolase Operasi SAR & Logo ANOA Rescue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan slot referensi Logo Ketiga (ANOA Rescue) pada Pengaturan Aplikasi dan sub-halaman khusus Dokumentasi Kolase Operasi SAR (4–12 foto/video, vertikal/persegi/horizontal) lengkap dengan ekspor gambar/video di browser dan arsip operasi.

**Architecture:** Logo ketiga mengikuti pola `logo_url_2` (`AppSettings.logo_url_3`, `getAppLogo3DataUri()`, `AdminController::prosesLogoSatker()`). Dokumentasi Operasi menggunakan tabel `OperasiDokumentasi` + `OperasiDokumentasiModel` + `OperasiDokumentasiApiController` (disinkronkan dengan `BackupService` dan `OperasiModel::delete()`), tab arsip `views/operasi/partials/tab_dokumentasi.php`, dan studio penuh `views/operasi/dokumentasi_studio.php` yang didukung modul layout murni `assets/js/dokumentasi/collage_layouts.js` serta renderer Canvas/MediaRecorder `assets/js/pages/operasi_dokumentasi.js`.

**Tech Stack:** PHP 8+, MySQL/InnoDB, Tailwind CSS v3, HTML5 Canvas 2D, MediaRecorder API, Node.js built-in test assertions.

**Spec:** `docs/superpowers/specs/2026-11-23-dokumentasi-kolase-operasi-sar-design.md`

## Global Constraints
- Dilarang memakai emoji di kode, komentar, maupun teks UI.
- Jumlah slot dokumentasi wajib berada pada rentang inklusif `4..12`.
- Batas ukuran upload server: gambar <= 12 MB, video <= 20 MB (selaras `.user.ini`).
- Seluruh berkas unggahan divalidasi MIME (`finfo`) + dimensi/magic-bytes, disimpan dengan nama acak di `uploads/operasi_dokumentasi/` ber-guard `index.php` 403.
- Semua 97 suite PHP + 7 suite JS eksisting wajib tetap lulus 100%.

## Review Focus
1. **Payload berbahaya menyamar gambar/video (XSS/PHP/spoofed MIME):** `OperasiDokumentasiModel::store()` wajib menolak file dengan MIME selain whitelist atau magic-bytes video invalid sebelum menyentuh DB.
2. **Jumlah slot di luar rentang 4–12 (mis. 3 atau 13):** ditolak di backend (`422`) dan dibatasi di `collage_layouts.js` (`clampSlotCount`).
3. **Penghapusan Operasi SAR meninggalkan file kolase yatim di disk:** `OperasiModel::delete()` wajib mengumpulkan dan menghapus file `file_path` + `thumb_path` setelah commit transaksi.
4. **Judul operasi panjang pada kanvas sempit (`9:16` Story):** `wrapCanvasLines()` wajib memecah teks dengan aman tanpa keluar batas atau menabrak grid media.
5. **Kolom `logo_url_3` atau tabel `OperasiDokumentasi` belum termigrasi saat halaman dibuka:** fallback aman tanpa error 500 (`getAppLogo3DataUri()` jatuh ke `assets/images/logo_anoa_rescue.png`, `OperasiDokumentasiModel::getByOperasiId()` memastikan skema atau mengembalikan `[]`).

---

### Task 1: Aset & Pengaturan Logo Ketiga (ANOA Rescue)
**Files:**
- Create: `assets/images/logo_anoa_rescue.png`
- Create: `database/migrations/2026_11_23_add_logo_url_3.sql`
- Modify: `config/app.php:484-495`
- Modify: `models/SettingsModel.php:56-77`
- Modify: `controllers/AdminController.php:175-182`
- Modify: `views/admin/partials/tab_satker.php:26-68`
- Test: `tests/test_logo_ketiga_anoa.php`

- [ ] Salin gambar ANOA Rescue ke `assets/images/logo_anoa_rescue.png`.
- [ ] Tulis tes TDD `tests/test_logo_ketiga_anoa.php` (RED), jalankan untuk memastikan gagal.
- [ ] Implementasikan migrasi `logo_url_3`, `getAppLogo3DataUri()`, update `SettingsModel`, `AdminController`, dan 3-kolom kartu logo di `views/admin/partials/tab_satker.php`.
- [ ] Jalankan migrasi & verifikasi `tests/test_logo_ketiga_anoa.php` (GREEN).

### Task 2: Migrasi, Model, BackupService, Cascade Delete & API `OperasiDokumentasi`
**Files:**
- Create: `database/migrations/2026_11_23_create_operasi_dokumentasi.sql`
- Create: `models/OperasiDokumentasiModel.php`
- Create: `api/controllers/OperasiDokumentasiApiController.php`
- Modify: `api/index.php:82-86`
- Modify: `services/BackupService.php:18-26`
- Modify: `models/OperasiModel.php:150-156,619-622,665-705`
- Modify: `database/migrate.php:125-135`
- Test: `tests/test_operasi_dokumentasi_backend.php`

- [ ] Tulis tes TDD `tests/test_operasi_dokumentasi_backend.php` mencakup: simpan gambar valid, simpan video WebM/MP4 + thumbnail valid, tolak slot_count 3 & 13, tolak file PHP/MIME palsu, hapus satu arsip + file fisik, hapus Operasi SAR membersihkan file fisik (cascade), dan `BackupService::MANAGED_TABLES`.
- [ ] Verifikasi tes gagal (RED).
- [ ] Implementasikan migrasi, model, API controller, routing, cascade delete, dan pendaftaran `BackupService`.
- [ ] Jalankan migrasi & verifikasi `tests/test_operasi_dokumentasi_backend.php` (GREEN).

### Task 3: Pure JS Collage Layout Engine (`collage_layouts.js`)
**Files:**
- Create: `assets/js/dokumentasi/collage_layouts.js`
- Test: `tests/js/collage_layouts.test.js`

- [ ] Tulis tes TDD `tests/js/collage_layouts.test.js` untuk semua preset ukuran (vertikal/persegi/horizontal), semua jumlah slot 4–12 (zero overlap, 100% dalam bounds, non-zero area), `computeCoverCrop` (portrait-in-landscape & zoom/pan clamp), `wrapCanvasLines`, dan `formatCollageFilename`.
- [ ] Verifikasi tes gagal (RED).
- [ ] Implementasikan `assets/js/dokumentasi/collage_layouts.js`.
- [ ] Verifikasi `node tests/js/collage_layouts.test.js` lulus 100% (GREEN).

### Task 4: Tab `Dokumentasi` & Sub-Halaman Studio Kolase Foto/Video SAR
**Files:**
- Create: `views/operasi/partials/tab_dokumentasi.php`
- Create: `views/operasi/dokumentasi_studio.php`
- Create: `assets/js/pages/operasi_dokumentasi.js`
- Modify: `controllers/OperasiController.php` (tambah route `action=dokumentasi` -> `dokumentasiStudio()`)
- Modify: `views/operasi/detail.php` (tambah tab `Dokumentasi` & render partial `tab_dokumentasi`)

- [ ] Tambah tab `Dokumentasi` pada `views/operasi/detail.php` dan partial arsip `views/operasi/partials/tab_dokumentasi.php`.
- [ ] Tambah handler `action=dokumentasi` pada `controllers/OperasiController.php`.
- [ ] Bangun tampilan studio `views/operasi/dokumentasi_studio.php` dan skrip interaktif Canvas + Video MediaRecorder `assets/js/pages/operasi_dokumentasi.js`.
- [ ] Rebuild `assets/css/tailwind.css` & perbarui `graphify`.

### Task 5: Verifikasi Menyeluruh (PHP, JS, & Chrome Live)
- [ ] Jalankan migrasi di `qrsar` dan `qrsar_test`.
- [ ] Jalankan seluruh suite JS (`tests/js/*.test.js`) dan seluruh suite PHP (`DB_DATABASE=qrsar_test php tests/run_all.php`).
- [ ] Verifikasi visual & interaktif di Chrome (Pengaturan Satker Logo 3, Detail Operasi Tab Dokumentasi, Studio Kolase 4–12 foto/video vertikal & horizontal, simpan arsip, hapus arsip, 0 error JS console).
