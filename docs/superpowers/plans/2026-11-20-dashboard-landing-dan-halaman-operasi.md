# Pemisahan Halaman Operasi SAR & Dashboard Baru — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task inline. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memindahkan halaman daftar operasi dari Dashboard menjadi menu & halaman **Operasi SAR (`/operasi.php`)**, serta membangun halaman **Dashboard baru (`/index.php`)** bergaya *Hybrid Command-Landing* yang bersih, sederhana, informatif, dan 100% kompatibel dengan seluruh tema Pengaturan Admin.

**Architecture:** Menambahkan 2 query skalar ringan (`OperasiModel::countOpen()` dan `GisModel::getStatsSummary()`), memindahkan view daftar operasi ke `views/operasi/list.php` untuk `OperasiController::index()`, memisahkan menu sidebar menjadi `Dashboard` dan `Operasi SAR` (+ badge `OPEN`), serta membangun `views/dashboard.php` baru memakai token CSS tema sistem (`--primary`, `--primary-light`, `.bento-card-v1`) dan pola kartu semantik stretched-link (`<article>` + `.dash-card-link`).

**Tech Stack:** Native PHP 8+, Tailwind CSS v3.4 (`npx -y tailwindcss@3.4.17`), CSS Custom Properties (`style.css` + `theme-minimal.css`), MySQL/MariaDB (`qrsar_test`).

**Spec:** [docs/superpowers/specs/2026-11-20-dashboard-landing-dan-halaman-operasi-design.md](file:///Users/irfandydk/antigravity/QR-SAR/docs/superpowers/specs/2026-11-20-dashboard-landing-dan-halaman-operasi-design.md)

## Global Constraints
- Kompatibel 100% pada 16 kombinasi tema: `data-ui="klasik|minimal"` × `data-theme="light|dark"` × `data-palette="orange|navy|emerald|gold"`.
- Zero query berat pada sidebar & dashboard (hanya query `COUNT(*)` skalar).
- Zero perubahan skema database dan zero regresi pada 94 test suite yang ada.
- Wajib kompilasi ulang `assets/css/tailwind.css` via `npx -y tailwindcss@3.4.17` dan jalankan `graphify update .`.
- Jangan commit/push/deploy tanpa perintah eksplisit dari pengguna.

## Review Focus
1. **Sidebar collapsed state + badge `OPEN`:** Saat sidebar diciutkan (`collapsed`), badge angka tidak boleh merusak lebar ikon atau meluber keluar rel 80px (gunakan `.sidebar-item-label` agar rapi menyembunyikan teks, dengan indikator titik kecil opsional pada ikon).
2. **Keamanan & akses peran `USER` vs `ADMIN`:** Di kartu fitur ke-6 Dashboard baru, akun `USER` diarahkan ke `/master.php` (bukan `/admin.php` yang terlarang 403 bagi `USER`), sementara `ADMIN` mendapat tautan ke `/admin.php` maupun `/master.php`.
3. **Kesesuaian tema `Minimal Glass` & `Dark Mode`:** Tidak boleh ada teks kontras rendah, latar belakang hardcode putih/gelap permanen, atau warna oranye mati yang tidak berubah saat palet diganti ke `Navy` / `Emerald` / `Gold`.
4. **Kompatibilitas test & route lama:** `/dashboard`, `/dashboard.php`, `/index.php`, `/` tetap merender Dashboard baru via `DashboardController::index()`; `/operasi` dan `/operasi.php` merender daftar Operasi SAR via `OperasiController::index()`; tombol kembali di `views/operasi/detail.php` tetap ke `/operasi.php`.
5. **Render tanpa error saat DB kosong (0 operasi / 0 BRA / 0 GIS):** Tidak ada division-by-zero, undefined array key, atau badge `0` yang tampil mengganggu di sidebar (badge sidebar hanya tampil jika `$openOpsCount > 0`).

---

### Task 1: Helper Statistik Ringan (`OperasiModel::countOpen()` & `GisModel::getStatsSummary()`)

**Files:**
- Modify: `models/OperasiModel.php`
- Modify: `models/GisModel.php`
- Create (Test): `tests/test_dashboard_landing_dan_operasi_split.php`

**Interfaces:**
- Produces:
  - `OperasiModel::countOpen(): int`
  - `GisModel::getStatsSummary(): array{points: int, polygons: int, patterns: int, routes: int, total: int}`

- [ ] **Step 1: Write failing test Section 1 in `tests/test_dashboard_landing_dan_operasi_split.php`**
  Verify `OperasiModel::countOpen()` matches actual `SELECT COUNT(*) FROM Operasi WHERE status = 'OPEN'` and `GisModel::getStatsSummary()` returns exact keys (`points`, `polygons`, `patterns`, `routes`, `total`) with `total === points + polygons + patterns + routes`.
- [ ] **Step 2: Run test to verify RED**
  Run: `DB_DATABASE=qrsar_test php tests/test_dashboard_landing_dan_operasi_split.php`
  Expected: FAIL (`method_exists` false).
- [ ] **Step 3: Implement `OperasiModel::countOpen()` and `GisModel::getStatsSummary()`**
  Single lightweight scalar SQL query each.
- [ ] **Step 4: Run lint & test to verify GREEN**
  Run: `php -l models/OperasiModel.php models/GisModel.php && DB_DATABASE=qrsar_test php tests/test_dashboard_landing_dan_operasi_split.php`
  Expected: Section 1 PASS.

---

### Task 2: Pemisahan Halaman Operasi SAR (`views/operasi/list.php`) & Menu Sidebar

**Files:**
- Create: `views/operasi/list.php` (memindahkan halaman daftar operasi dari `views/dashboard.php`)
- Modify: `controllers/OperasiController.php:197-205` (render `'operasi/list'`, `title => 'Operasi SAR'`, `navbarTitle => 'Operasi SAR'`)
- Modify: `views/layout/sidebar.php:10-70` (memisahkan item `Dashboard` → `/index.php` dan `Operasi SAR` → `/operasi.php` dengan badge `OPEN` bila `> 0`)
- Modify (Tests): `tests/test_dashboard_default_open.php:25-33`, `tests/test_tahap3_frontend_gis_print.php:64-70` (arahkan inspeksi daftar operasi ke `views/operasi/list.php`)
- Update (Test): `tests/test_dashboard_landing_dan_operasi_split.php` (Section 2)

**Interfaces:**
- Consumes: `OperasiModel::countOpen()`
- Produces:
  - Canonical view `views/operasi/list.php`
  - Sidebar distinct links `/index.php` (`Dashboard`, active only on `dashboard`) and `/operasi.php` (`Operasi SAR`, active on `operasi|operasi_detail|operasi_form`, with `$openOpsCount` badge)

- [ ] **Step 1: Add failing Section 2 assertions to `tests/test_dashboard_landing_dan_operasi_split.php`**
- [ ] **Step 2: Run test to verify Section 2 RED**
- [ ] **Step 3: Copy `views/dashboard.php` → `views/operasi/list.php`, update `OperasiController.php`, update `views/layout/sidebar.php`, and update the 2 legacy test paths**
- [ ] **Step 4: Verify lint & tests GREEN**
  Run: `php -l views/operasi/list.php controllers/OperasiController.php views/layout/sidebar.php && DB_DATABASE=qrsar_test php tests/test_dashboard_default_open.php && DB_DATABASE=qrsar_test php tests/test_tahap3_frontend_gis_print.php && DB_DATABASE=qrsar_test php tests/test_dashboard_landing_dan_operasi_split.php`
  Expected: ALL PASS.

---

### Task 3: Membangun Halaman Dashboard Baru (`DashboardController.php` & `views/dashboard.php`)

**Files:**
- Modify: `controllers/DashboardController.php`
- Overwrite: `views/dashboard.php` (Hybrid Command-Landing 5 blok, theme-compatible, stretched-link `<article>` cards)
- Rebuild: `assets/css/tailwind.css` via `npx -y tailwindcss@3.4.17`
- Update (Test): `tests/test_dashboard_landing_dan_operasi_split.php` (Section 3: struktur 5 blok, token tema, peran USER vs ADMIN, render output bersih tanpa error PHP)

**Interfaces:**
- Consumes: `OperasiModel::getStats()`, `GisModel::getStatsSummary()`, `SettingsModel::getSettings()`, `AuthHelper::getCurrentUser()`, `AuthHelper::isAdmin()`
- Produces: Rendered semantic landing dashboard in `views/dashboard.php`

- [ ] **Step 1: Add failing Section 3 assertions to `tests/test_dashboard_landing_dan_operasi_split.php`**
- [ ] **Step 2: Run test to verify Section 3 RED**
- [ ] **Step 3: Implement `DashboardController.php` and `views/dashboard.php`**
- [ ] **Step 4: Rebuild Tailwind CSS and verify GREEN**
  Run: `npx -y tailwindcss@3.4.17 -c tailwind.config.js -i assets/css/tailwind.src.css -o assets/css/tailwind.css --minify && php -l controllers/DashboardController.php views/dashboard.php && DB_DATABASE=qrsar_test php tests/test_dashboard_landing_dan_operasi_split.php`
  Expected: Build exit 0, lint OK, ALL assertions PASS.

---

### Task 4: Full Verification, Live Browser Theme Matrix Check & Graphify Update

**Files:**
- Verify all 7 JS tests + 95 PHP test suites + Live Browser (`Klasik Light/Dark`, `Navy/Emerald/Gold` palette, `Minimal Glass Light/Dark`, `/index.php`, `/operasi.php`) + `graphify update .`

- [ ] **Step 1: Run full JS tests + full PHP test runner (`DB_DATABASE=qrsar_test php tests/run_all.php`)**
- [ ] **Step 2: Run live browser visual & functional check via `browser` subagent**
- [ ] **Step 3: Run `graphify update .`**
