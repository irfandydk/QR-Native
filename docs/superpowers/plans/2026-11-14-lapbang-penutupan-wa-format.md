# Format WA LAPBANG & Penutupan + Input Cuaca Terpisah (Opsi A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menjadikan hasil teks WhatsApp `LAPBANG` dan `PENUTUPAN` (pratinjau maupun tersimpan) persis sesuai format baku Basarnas KPP Kendari, lengkap dengan kolom terpisah `cuaca`, `gelombang`, dan `angin` (Opsi A).

**Architecture:** Menambahkan migrasi kolom `gelombang` & `angin` pada `LaporanHarian` + `LaporanModel::ensureSchemaUpdated()`, menyelaraskan pewarisan nilai di `HierarchyHelper`, `OperasiController`, dan `OperasiApiController`, menambah 2 input cuaca pada `views/operasi/sitrep_form.php` (+ memperbaiki listener/draft JS di `assets/js/pages/sitrep_form.js`), serta memperbarui `WhatsAppTemplateService` (`getHeaderWA`, `getFooterWA`, `formatAlutLines`, `generateLapbangWA`).

**Tech Stack:** PHP 8+, MySQL (PDO), Vanilla JS, Tailwind CSS, CLI Test Runner (`tests/run_all.php`, `node --test`).

**Spec:** `docs/superpowers/specs/2026-11-14-lapbang-penutupan-wa-format-design.md`

## Global Constraints
- `getHeaderWA()`: Top & bottom `————————-` (8 em-dash + 1 hyphen) around `FILE AKTIF`, blank line after `PROFESIONAL MODERN TERUJI`, `mohon izin` (bukan `ijin`), divider `====================================` (36 `=`).
- `getFooterWA()`: Period after `arahan.`, 50 `=`, lowercase `dan`, `www.kendari.basarnas.go.id`, `sar304kendari@gmail.com / pusdatinsarkendari@yahoo.co.id`.
- `generateLapbangWA()`: Unnumbered headers with colons, `- ` bullets for `Hasil Ops SAR`, `Data Korban`, `Unsur SAR Terlibat`, `Alut dan Palsar`, Title Case `Orang`, `Kondisi Cuaca:` with `• cuaca : ...`, `• Gelombang : ...`, `• Angin: ...`.
- One Alut WA formatter (`formatAlutLines` parameterized, no duplicate sort logic).
- DB migration follows `database/migrations/` pattern + idempotent guard for CLI/unit tests.

## Review Focus
- `nama_operasi` yang sudah diawali `Operasi SAR` / `Ops SAR` tidak boleh menggandakan kata `Operasi SAR` di pembuka header maupun kalimat debriefing.
- Isi laporan (`isi_laporan`) dengan baris polos, `• `, `* `, atau yang sudah mencantumkan kalimat `debriefing` penutupan tidak boleh kehilangan bullet atau menggandakan paragraf debriefing.
- Variasi kondisi korban saat Penutupan (korban ditemukan seluruhnya vs masih DP/H atau nol) menghasilkan kalimat penutupan yang akurat dan tetap menampilkan `Orang` Title Case atau `-` untuk nol.
- Pembaruan `LaporanModel::update()` secara parsial (tanpa key `gelombang`/`angin`) atau data lama dengan kolom cuaca kosong tetap mewarisi data `LaporanHarian`/`Operasi` secara aman tanpa error SQL bahkan bila migrasi baru dijalankan.
- Pratinjau WA (`wa-preview` API) & edit Penutupan mempertahankan `hari_ke` saat tanggal laporan tidak berubah dan memperbarui isi modal `#modal-preview-sitrep-wa` ketika status korban diganti.

---

### Task 1: Database Migration & Weather Persistence in `LaporanModel` + `HierarchyHelper`

**Files:**
- Create: `database/migrations/2026_11_14_add_weather_to_laporan_harian.sql`
- Modify: `database/migrate.php:118-126`
- Modify: `models/LaporanModel.php:118-270`
- Modify: `helpers/HierarchyHelper.php:199-256, 378-448`
- Test: `tests/test_lapbang_penutupan_wa_format.php` (initial DB + persistence assertions)

**Interfaces:**
- Produces:
  - `LaporanModel::ensureSchemaUpdated(bool $force = false): void`
  - `LaporanModel::create(array $data): int` persists `cuaca`, `gelombang`, `angin`
  - `LaporanModel::update(int $id, array $data): bool` persists `cuaca`, `gelombang`, `angin` preserving prior values when keys omitted
  - `HierarchyHelper::getNextLapbangDefaults(array $ops): array` includes `'inherited_cuaca'`, `'inherited_gelombang'`, `'inherited_angin'`

- [ ] **Step 1: Write failing persistence test in `tests/test_lapbang_penutupan_wa_format.php`**
  Verify `SHOW COLUMNS FROM LaporanHarian LIKE 'gelombang'` and `'angin'` fail initially, and `LaporanModel::create()`/`update()`/`getById()` persist `cuaca`, `gelombang`, `angin` cleanly (including partial update without omitting existing `gelombang`/`angin`), plus `HierarchyHelper::getNextLapbangDefaults()` returns `'inherited_gelombang'` and `'inherited_angin'`.

- [ ] **Step 2: Run test to verify it fails**
  Run: `DB_DATABASE=qrsar_test php tests/test_lapbang_penutupan_wa_format.php`
  Expected: FAIL on missing columns `gelombang`/`angin` and missing `inherited_gelombang`.

- [ ] **Step 3: Create migration SQL and update `database/migrate.php`, `models/LaporanModel.php`, and `helpers/HierarchyHelper.php`**
  - Create `database/migrations/2026_11_14_add_weather_to_laporan_harian.sql` adding `gelombang` and `angin` (`VARCHAR(191) NULL DEFAULT NULL`) after `cuaca`.
  - Add `LaporanModel::ensureSchemaUpdated(bool $force = false): void` and call it in `database/migrate.php`.
  - In `LaporanModel::create()` and `update()`, detect weather columns cleanly (`hasWeatherColumns(PDO $db)`) and bind `:cuaca`, `:gelombang`, `:angin`; on partial `update()` without `'gelombang'`/`'angin'` keys in `$data`, keep `$lap['gelombang']` / `$lap['angin']`, and merge `'cuaca'`, `'gelombang'`, `'angin'` into `$waPayload` passed to `generateLapbangWA()` when missing.
  - Run `DB_DATABASE=qrsar_test php database/migrate.php` and also migrate dev database if configured? Wait: only touch test/migration script; `migrate.php` can be run on `qrsar_test` now.
  - Update `HierarchyHelper::getNextLapbangDefaults()` to initialize and inherit `inherited_cuaca`, `inherited_gelombang`, `inherited_angin` from `LaporanHarian` or `Operasi` (separating the default `'Cerah Berawan'` from waves/wind).

- [ ] **Step 4: Run test to verify it passes**
  Run: `DB_DATABASE=qrsar_test php database/migrate.php && DB_DATABASE=qrsar_test php tests/test_lapbang_penutupan_wa_format.php`
  Expected: PASS on schema, persistence, and inheritance checks.

- [ ] **Step 5: Commit**
  ```bash
  git add database/migrations/2026_11_14_add_weather_to_laporan_harian.sql database/migrate.php models/LaporanModel.php helpers/HierarchyHelper.php tests/test_lapbang_penutupan_wa_format.php
  git commit -m "feat(lapbang): add gelombang and angin columns with inheritance"
  ```

---

### Task 2: Align WhatsApp Header, Footer, and `generateLapbangWA()` Format

**Files:**
- Modify: `services/WhatsAppTemplateService.php:20-66, 106-116, 342-428`
- Modify: `tests/test_wa_jarak_korban.php:42-45`
- Modify: `tests/test_alut_kategori_keluaran.php:33-56`
- Modify: `tests/test_audit_optimalisasi_menyeluruh.php:172`
- Modify: `tests/test_lapbang_penutupan_wa_format.php` (add full exact-match, normalization, Review Focus edge cases)

**Interfaces:**
- Consumes: `SarCalculationService::{formatTanggalIndo, formatTanggalSimple, formatJamWITA, hitungStatistikKorban, getLabelStatusKorban}`, `AlutKategori::sort`
- Produces:
  - Updated `WhatsAppTemplateService::getHeaderWA(...)`, `getFooterWA()`, `formatAlutLines(array $alut, string $bullet = '•'): string`, `generateLapbangWA(...)`

- [ ] **Step 1: Add failing WA format assertions in `tests/test_lapbang_penutupan_wa_format.php`**
  Cover:
  1. Full exact character-for-character match against the user's `LAPBANG OPS SAR H.7 DAN PENUTUPAN` target example (all headers, motto spacing, opening sentence, `====================================`, `Hasil Ops SAR H.7:`, 6 hyphen bullets, `pukul 12.10 WITA`, `Data Korban: 1 Orang`, `Identitas Korban:`, `Unsur SAR Terlibat:`, `Alut dan Palsar:`, `Kondisi Cuaca:` with `(Sumber BMKG)`, footer period, 50 `=`, lowercase site/emails).
  2. Review Focus 1: `$ops['nama_operasi'] = 'Operasi SAR Uji Duplikat'` produces `'Operasi SAR Uji Duplikat'` (no `'Operasi SAR Operasi SAR'`).
  3. Review Focus 2: Plain multi-line `isi_laporan` gets `- ` bullets; `• ` / `* ` bullets normalize to `- `; if `isi_laporan` already includes `debriefing penutupan`, debriefing clause is NOT duplicated.
  4. Review Focus 3: Non-penutupan `PERKEMBANGAN` report uses `"Perkembangan Operasi SAR ..."` and omits debriefing; H.7 nihil (`DP > 0`) uses `"Dengan selesainya seluruh rangkaian pencarian, Operasi SAR dinyatakan ditutup..."`.
  5. Review Focus 4: Weather fallback to `$ops['cuaca']`/`$ops['gelombang']`/`$ops['angin']`, then `'-'`; `(Sumber BMKG)` not duplicated when already present; omitted when wind is `'-'`.

- [ ] **Step 2: Run tests to verify they fail**
  Run: `DB_DATABASE=qrsar_test php tests/test_lapbang_penutupan_wa_format.php`
  Expected: FAIL on WA format diff.

- [ ] **Step 3: Implement formatting updates in `services/WhatsAppTemplateService.php` & update legacy assertions**
  - Update `getHeaderWA()` and `getFooterWA()`.
  - Add helper `formatOperasiSubject(array $ops): string` and `formatHasilOpsLines(string $isi): string`.
  - Parameterize `formatAlutLines(array $alut, string $bullet = '•'): string` (preserving `•` default for Laporan Awal & RENOPS).
  - Rewrite `generateLapbangWA()` section layout, closure clause, Title Case `'Orang'`, `- ` bullets, and `Kondisi Cuaca:` block.
  - Update expectations in `tests/test_wa_jarak_korban.php` (`- MD : -`), `tests/test_alut_kategori_keluaran.php` (`- ` for `generateLapbangWA` + DRY source check), and `tests/test_audit_optimalisasi_menyeluruh.php` (`- S : 1 Orang`).

- [ ] **Step 4: Run tests to verify they pass**
  Run: `DB_DATABASE=qrsar_test php tests/test_lapbang_penutupan_wa_format.php && DB_DATABASE=qrsar_test php tests/test_wa_jarak_korban.php && DB_DATABASE=qrsar_test php tests/test_alut_kategori_keluaran.php && DB_DATABASE=qrsar_test php tests/test_audit_optimalisasi_menyeluruh.php`
  Expected: PASS on all 4 scripts.

- [ ] **Step 5: Commit**
  ```bash
  git add services/WhatsAppTemplateService.php tests/test_lapbang_penutupan_wa_format.php tests/test_wa_jarak_korban.php tests/test_alut_kategori_keluaran.php tests/test_audit_optimalisasi_menyeluruh.php
  git commit -m "feat(wa): align LAPBANG & Penutupan header, body, weather, and footer format"
  ```

---

### Task 3: UI Form Inputs, Controller Wiring, Preview API, & SITREP JS Fixes

**Files:**
- Modify: `controllers/OperasiController.php:874-920`
- Modify: `views/operasi/sitrep_form.php:16-39, 211-221`
- Modify: `api/controllers/OperasiApiController.php:52-56`
- Modify: `assets/js/pages/sitrep_form.js:71-96, 613-644, 657-660, 714-738`
- Modify: `tests/test_lapbang_penutupan_wa_format.php` (add view rendering + controller/API preview + E2E create/edit checks)

**Interfaces:**
- Consumes: `LaporanModel::{create, update, buildPreviewWa}`, `HierarchyHelper::getNextLapbangDefaults`
- Produces: Form inputs `cuaca` (`#sitrep_cuaca`), `gelombang` (`#sitrep_gelombang`), `angin` (`#sitrep_angin`) rendered in `sitrep_form.php`, populated by `OperasiController::sitrepForm()`, previewed consistently by `/operasi/wa-preview`, and persisted end-to-end.

- [ ] **Step 1: Add failing integration/view tests in `tests/test_lapbang_penutupan_wa_format.php`**
  Cover:
  1. `views/operasi/sitrep_form.php` renders inputs `name="cuaca"`, `name="gelombang"`, `name="angin"` with XSS-escaped values.
  2. End-to-end DB `LaporanModel::create()` -> `getById()` -> `buildPreviewWa()` -> `LaporanModel::update()` produces identical WA text between live preview and saved `full_text_wa`.
  3. Review Focus 5: `OperasiApiController` keeps `$existing['hari_ke']` when editing an existing report whose date didn't change; `sitrep_form.js` references valid `#modal-preview-sitrep-wa` / `previewSitrepWa()` and skips initial overwrite of `#sitrep_hari_ke` in edit mode.

- [ ] **Step 2: Run test to verify it fails**
  Run: `DB_DATABASE=qrsar_test php tests/test_lapbang_penutupan_wa_format.php`
  Expected: FAIL on missing `gelombang` / `angin` form fields.

- [ ] **Step 3: Implement Controller, View, API Preview, and JS fixes**
  - Update `OperasiController::sitrepForm()` to resolve and pass `$cuaca`, `$gelombang`, `$angin` (edit mode falls back to `$operasi`, create mode falls back to `$nextLapbang['inherited_*']` then `$operasi`).
  - Update `views/operasi/sitrep_form.php` PHPDoc/fallbacks and convert single weather row to 3-column responsive grid (`Kondisi Cuaca (BMKG)`, `Tinggi Gelombang`, `Arah & Kecepatan Angin`) matching `views/operasi/form.php`.
  - Update `api/controllers/OperasiApiController.php` `sitrep` preview branch to preserve `$existing['hari_ke']` when `$existing` has unchanged date, mirroring `OperasiController::sitrepForm()`.
  - Update `assets/js/pages/sitrep_form.js`:
    - `applyH7ClosureTemplate()` & `applyH7ExtensionTemplate()` use `- ` bullets (and omit duplicating the debriefing line since `waktu_debrief` generates it cleanly unless customized).
    - Fix draft save/restore IDs (`sitrep_waktu_laporan`, `sitrep_waktu_debrief`, `sitrep_isi_laporan`, `sitrep_cuaca`, `sitrep_gelombang`, `sitrep_angin`).
    - Fix `initVictimStatusListeners()` selector `#modal-preview-sitrep-wa` and `previewSitrepWa()`.
    - Only run `recalculateSitrepHariKe()` on `DOMContentLoaded` and `submit` when `!isSitrepEditMode` so saved `hari_ke` isn't clobbered during edit.

- [ ] **Step 4: Run full verification suites**
  Run:
  - `DB_DATABASE=qrsar_test php tests/test_lapbang_penutupan_wa_format.php`
  - `DB_DATABASE=qrsar_test php tests/run_all.php`
  - `node --test tests/js/*.test.js`
  - `node -c assets/js/pages/sitrep_form.js`
  Expected: 100% PASS, zero JS syntax errors.

- [ ] **Step 5: Commit**
  ```bash
  git add controllers/OperasiController.php views/operasi/sitrep_form.php api/controllers/OperasiApiController.php assets/js/pages/sitrep_form.js tests/test_lapbang_penutupan_wa_format.php
  git commit -m "feat(sitrep): add separated weather fields and align preview/draft handling"
  ```
