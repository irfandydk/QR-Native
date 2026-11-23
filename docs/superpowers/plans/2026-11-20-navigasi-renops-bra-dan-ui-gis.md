# Optimasi Navigasi (RENOPS Capture & BRA) & UI Halaman Ploting Peta GIS — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task inline (direct single-agent execution requested by user approval). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengoptimalkan 2 alur navigasi (return same-tab Capture Peta RENOPS ke draf asal dan tombol Kembali BRA ke sub-tab BRA Detail Operasi) serta memodernisasi UI Halaman Ploting Peta GIS (header tunggal ramping, 3 tombol bulat melayang untuk Pencarian/Jenis Peta/Unit, dan Sidebar Tools yang lebih lega serta ringkas).

**Architecture:** Perubahan navigasi berbasis parameter query konteks ketat yang divalidasi (integer positif saja, tanpa risiko open redirect, dan tanpa mengubah skema DB). Perubahan UI GIS menyatukan header duplikat di `views/layout/navbar.php` + `views/gis.php`, memindahkan 3 kontrol peta ke dock vertikal melayang di dalam `#gisMapWrapper` sambil mempertahankan 100% DOM ID yang dikunci test suite, serta memusatkan state collapse sidebar melalui kelas CSS `.gis-sidebar-collapsed`.

**Tech Stack:** Native PHP 8+, Vanilla JS (ES6), Tailwind CSS v3.4 (`npm run build:css`), Leaflet.js, MySQL/MariaDB (`qrsar_test`).

**Spec:** [docs/superpowers/specs/2026-11-20-navigasi-renops-bra-dan-ui-gis-design.md](file:///Users/irfandydk/antigravity/QR-SAR/docs/superpowers/specs/2026-11-20-navigasi-renops-bra-dan-ui-gis-design.md)

## Global Constraints
- Pertahankan 100% seluruh DOM ID dan string badge yang diuji oleh 92 PHP test suite (termasuk `#gisGlobalUnitSwitcher`, `.global-unit-pill-btn`, `.basemap-pill-btn`, `#locationSearchBarWrapper`, `#locationSearchInput`, `#locationSearchResultsPopover`, `#locationSearchResultsList`, `#btnToggleCaptureMode`, `#btnToggleExportMenu`, `#btnOpenImportKmlModal`, `#btnToggleFullscreen`, `Search Area (<span id="polygonsBadgeCount">`, `Pola SAR (<span id="patternsBadgeCount"><?= $initialPatternsCount ?></span>)`, `Jarak & ETA (<span id="routesBadgeCount"><?= $initialRoutesCount ?></span>)`).
- Jangan mengubah parameter `from` di `views/bra/form.php` (sudah dipakai generator auto-draft `BraTemplates`). Gunakan `return_to=operasi_bra&return_op=<positive_int>`.
- Jangan mengirim `capture_ref` pada Capture Peta LKP (`capture_doc=RENOPS`) agar penyimpanan snapshot LKP tetap 1x per operasi (`id_referensi = NULL`). Gunakan parameter konteks terpisah `from_renops`.
- Seluruh fitur UI GIS wajib bekerja 100% offline tanpa CDN eksternal dan tetap bekerja di mode terang, mode gelap, tema klasik, tema minimal, dan mode Fullscreen.
- Wajib jalankan `npm run build:css` setelah perubahan kelas Tailwind di view dan `graphify update .` setelah modifikasi kode selesai.
- Jangan commit/push/deploy tanpa perintah eksplisit dari pengguna.

## Review Focus
1. **Pergantian operasi di panel Capture setelah dibuka dari draf RENOPS:** Jika operator membuka Capture LKP dari RENOPS Hari ke-2 Operasi #10 lalu mengganti dropdown operasi ke Operasi #11 di panel Capture, tautan kembali tidak boleh mengarah ke `id_renops` milik Operasi #10 — `fromRenopsParam` wajib dibatasi hanya saat `idOp === initialData.currentOperasiId`.
2. **Manipulasi parameter URL `return_to` / `return_op` pada BRA (Open Redirect / XSS):** Input seperti `return_to=//evil.com` atau `return_op=0` atau `return_op=1abc` harus ditolak secara ketat dan fallback aman ke `/bra.php`.
3. **Alur lengkap BRA `Detail Operasi → Lihat BRA → Edit Form → Simpan → Lihat BRA → Kembali`:** Konteks `return_to=operasi_bra&return_op=<id>` harus bertahan melintasi view, form edit, hidden input POST, dan redirect `BraController::save()`.
4. **Tumpang tindih 3 tombol bulat melayang dengan panel Capture 16:9 & mode Fullscreen:** Saat `openCaptureMode()` aktif, `#gisFloatingMapDock` wajib disembunyikan (`hidden`) agar tidak tertumpuk di bawah `#captureControlPanel` (`top-3 right-3`), dan muncul kembali saat `closeCaptureMode()`.
5. **Kontras mode terang & gelap pada tombol yang class-nya ditimpa JS (`setBasemapLayer`, `setGlobalMeasurementUnit`, `updateDraggableLockUI`):** Tidak boleh ada tombol tidak aktif yang memaksa teks gelap pada latar gelap atau latar gelap permanen pada mode terang.

---

### Task 1: Navigasi #1 — Same-Tab Return Capture Peta LKP & Rencana ke Draf RENOPS

**Files:**
- Modify: `views/operasi/partials/renops_smeac_doc.php:100-114`
- Modify: `views/gis.php:34-40,1778-1796`
- Modify: `assets/js/gis_master.js:8361-8406`
- Create (Test): `tests/test_navigasi_renops_bra_dan_gis_ui.php`

**Interfaces:**
- Produces:
  - Query param `from_renops=<int>` on LKP & Rencana capture URLs from `renops_smeac_doc.php`
  - `window.SAR_INITIAL_GIS_DATA.fromRenopsParam` (`int|null`) in `views/gis.php`
  - Same-tab anchor in `updateSavedStatusBanner()` without `target="_blank"`

- [ ] **Step 1: Write failing test suite (Section 1: RENOPS Capture Navigation)**
  Create `tests/test_navigasi_renops_bra_dan_gis_ui.php` with assertions verifying:
  - `renops_smeac_doc.php` appends `from_renops=` on both `$capturePetaUrl` (without adding `capture_ref=` to LKP!) and `$capturePetaRencanaUrl`.
  - `views/gis.php` parses `$_GET['from_renops']` and exposes `fromRenopsParam` in `SAR_INITIAL_GIS_DATA`.
  - `assets/js/gis_master.js` `updateSavedStatusBanner()` does NOT contain `target="_blank"`, and `getPrintUrlForSelection()` prioritizes `fromRenopsParam` scoped to matching `currentOperasiId`.
- [ ] **Step 2: Run test to verify RED**
  Run: `DB_DATABASE=qrsar_test php tests/test_navigasi_renops_bra_dan_gis_ui.php`
  Expected: FAIL on all RENOPS Capture assertions.
- [ ] **Step 3: Implement changes in `renops_smeac_doc.php`, `views/gis.php`, and `assets/js/gis_master.js`**
  - `views/operasi/partials/renops_smeac_doc.php`: append `&from_renops=' . (int)($meta['id_renops'] ?? 0)` to both `$capturePetaUrl` and `$capturePetaRencanaUrl`.
  - `views/gis.php`: parse `$fromRenopsParam` (positive int or `null`) and emit `fromRenopsParam: <?= jsonSafe($fromRenopsParam) ?>`.
  - `assets/js/gis_master.js`:
    - Update `getPrintUrlForSelection(idOp, jenis, idRef)` to check `fromRenops = (initialData.fromRenopsParam && parseInt(idOp, 10) === parseInt(initialData.currentOperasiId || 0, 10)) ? initialData.fromRenopsParam : null;`, resolving `ref = idRef || fromRenops || (cachedOperasiRenopsList[0] ? cachedOperasiRenopsList[0].id_renops : '')`.
    - Remove `target="_blank"` from the anchor in `updateSavedStatusBanner()`, and show contextual label `← Kembali ke Draf RENOPS` when returning to `fromRenops` (else `🖨️ Lihat Cetakan`, always same-tab).
- [ ] **Step 4: Run test & syntax checks to verify GREEN**
  Run: `php -l views/operasi/partials/renops_smeac_doc.php views/gis.php && node --check assets/js/gis_master.js && DB_DATABASE=qrsar_test php tests/test_navigasi_renops_bra_dan_gis_ui.php`
  Expected: PASS on Section 1.

---

### Task 2: Navigasi #2 — Context-Aware BRA SAR Back Button ke Sub-tab BRA Detail Operasi

**Files:**
- Modify: `views/operasi/partials/tab_bra.php:14-60`
- Modify: `controllers/BraController.php:11-285`
- Modify: `views/bra/view.php:45-100`
- Modify: `views/bra/form.php:20-35,1270-1285` (and hidden inputs inside `<form>`)
- Update (Test): `tests/test_navigasi_renops_bra_dan_gis_ui.php`

**Interfaces:**
- Produces:
  - `BraController::resolveReturnContext(?array $source = null): array` returning `['returnTo' => string, 'returnOp' => int, 'backUrl' => string, 'backLabel' => string, 'returnQuery' => string]`
  - Strict validation: only `return_to === 'operasi_bra'` with `preg_match('/^[1-9][0-9]{0,9}$/', $rawOp)` resolves `backUrl = BASE_URL . '/operasi_detail.php?id=' . $opId . '&tab=bra'`; everything else resolves `backUrl = BASE_URL . '/bra.php'`.

- [ ] **Step 1: Add failing assertions for BRA return navigation (Section 2)**
  Append assertions to `tests/test_navigasi_renops_bra_dan_gis_ui.php` testing:
  - `tab_bra.php` includes `return_to=operasi_bra&return_op=` on View, Edit, and Create links.
  - `BraController::resolveReturnContext()` (tested directly in PHP!) returns `/operasi_detail.php?id=42&tab=bra` for valid `['return_to' => 'operasi_bra', 'return_op' => '42']`, and falls back safely to `/bra.php` for invalid/malicious inputs (`//evil.com`, `0`, `-5`, `42abc`, missing params).
  - `views/bra/view.php` uses `$backUrl` on Back button and appends `$returnQuery` on Edit Form link.
  - `views/bra/form.php` uses `$backUrl` on Batal button and includes hidden inputs `return_to` and `return_op`.
  - `BraController::save()` preserves `$returnQuery` on redirects.
- [ ] **Step 2: Run test to verify RED on Section 2**
  Run: `DB_DATABASE=qrsar_test php tests/test_navigasi_renops_bra_dan_gis_ui.php`
  Expected: FAIL on Section 2 assertions.
- [ ] **Step 3: Implement BRA return context across `tab_bra.php`, `BraController.php`, `views/bra/view.php`, `views/bra/form.php`**
  - Add `public static function resolveReturnContext(?array $source = null): array` to `BraController`.
  - Pass `$backUrl`, `$backLabel`, `$returnTo`, `$returnOp`, `$returnQuery` into `views/bra/view.php` and `views/bra/form.php`.
  - Preserve `$returnQuery` across `BraController::save()` redirects.
  - Wire `$backUrl` and `$returnQuery` in `views/bra/view.php` and `views/bra/form.php` (with safe fallback if view is ever rendered directly in unit tests without controller variables!).
- [ ] **Step 4: Run test & lint checks to verify GREEN**
  Run: `php -l controllers/BraController.php views/operasi/partials/tab_bra.php views/bra/view.php views/bra/form.php && DB_DATABASE=qrsar_test php tests/test_navigasi_renops_bra_dan_gis_ui.php`
  Expected: PASS on Sections 1 & 2.

---

### Task 3: UI Halaman Ploting Peta — Unified Header, 3 Floating Circular Controls & Compact Spacious Sidebar

**Files:**
- Modify: `views/layout/navbar.php:1-62`
- Modify: `assets/css/style.css:332-353`
- Modify: `views/gis.php:170-1285` (styles, unified header, simplified sidebar, floating map controls dock)
- Modify: `assets/js/gis_master.js` (`setGlobalMeasurementUnit`, `setBasemapLayer`, `updateDraggableLockUI`, `setSidebarVisibility` helper replacing 4x `-ml-[390px]`, `initFloatingMapControls`, `openCaptureMode`/`closeCaptureMode` hiding `#gisFloatingMapDock`, `bootstrapApp` wiring)
- Rebuild: `assets/css/tailwind.css` via `npm run build:css`
- Update (Test): `tests/test_navigasi_renops_bra_dan_gis_ui.php`

**Interfaces:**
- Produces:
  - Unified header `#gisUnifiedHeader` with `#mobile-menu-btn` & `#theme-toggle-btn` + `#theme-icon`
  - Floating vertical map toolbar `#gisFloatingMapDock` (`role="toolbar"`, `aria-orientation="vertical"`, `focusgroup="toolbar wrap block"`) with triggers `#btnFloatingSearchToggle`, `#btnFloatingBasemapToggle`, `#btnFloatingUnitToggle` and popovers `#floatingSearchPopover`, `#floatingBasemapPopover`, `#floatingUnitPopover` + `#floatingUnitBadge`
  - Centralized `setSidebarVisibility(visible)` helper using `.gis-sidebar-collapsed` class (`--gis-sidebar-w: 360px`)
  - Compact single-row tab toolbars preserving every legacy DOM ID and badge markup

- [ ] **Step 1: Add failing assertions for GIS UI (Section 3)**
  Append assertions to `tests/test_navigasi_renops_bra_dan_gis_ui.php` checking:
  - `views/layout/navbar.php` skips generic `<header>` when `$currentRoute === 'gis'`.
  - `assets/css/style.css` sets `height: 100vh !important` for `body.route-gis .page-body`.
  - `views/gis.php` renders `#gisUnifiedHeader` containing `#mobile-menu-btn`, `#theme-toggle-btn`, `#theme-icon`, Logo Basarnas, `GIS RADAR OPERASI SAR KENDARI`, `#selectGisOperasi`.
  - `views/gis.php` renders `#gisFloatingMapDock` inside `#gisMapWrapper` with `role="toolbar"`, `aria-orientation="vertical"`, `focusgroup="toolbar wrap block"`, `#btnFloatingSearchToggle`, `#btnFloatingBasemapToggle`, `#btnFloatingUnitToggle`, `#floatingUnitBadge`, and wraps existing `#locationSearchBarWrapper`, `.basemap-pill-btn`, and `#gisGlobalUnitSwitcher`.
  - `views/gis.php` sidebar uses `.gis-sidebar-panel` (`360px`) and compact action buttons (no bulky full-width `w-full py-2.5` on `#btnShowPointCreator`, `#btnStartNewPolygon`, `#btnStartNewPattern`, `#btnStartNewRoute`), while preserving all legacy DOM IDs and exact badge strings.
  - `assets/js/gis_master.js` implements `initFloatingMapControls()`, hides `#gisFloatingMapDock` in `openCaptureMode()` and restores in `closeCaptureMode()`, syncs `#floatingUnitBadge`, uses `.gis-sidebar-collapsed` (0 remaining `-ml-[390px]`), and includes roving-tabindex fallback when `!('focusGroup' in HTMLElement.prototype)`.
- [ ] **Step 2: Run test to verify RED on Section 3**
  Run: `DB_DATABASE=qrsar_test php tests/test_navigasi_renops_bra_dan_gis_ui.php`
  Expected: FAIL on Section 3 assertions.
- [ ] **Step 3: Implement `views/layout/navbar.php` & `assets/css/style.css`**
  - Wrap generic `<header>` in `views/layout/navbar.php` with `<?php if (($currentRoute ?? '') !== 'gis'): ?> ... <?php endif; ?>` (always keeping `<main class="page-body">` outside the `if`).
  - Update `body.route-gis .page-body` in `assets/css/style.css` to `height: 100vh !important;`.
- [ ] **Step 4: Implement `views/gis.php` (CSS tokens, Unified Header, Compact Sidebar, Floating Circular Controls Dock)**
  - Add clean scoped CSS rules in `views/gis.php` for `.gis-sidebar-panel`, `.gis-sidebar-panel.is-collapsed`, `.gis-fab-btn` (40×40px, 44×44px `@media (pointer: coarse)`, `:focus-visible`, `@media (hover: hover)`, `@media (prefers-reduced-motion: no-preference)`), and `.gis-fab-popover`.
  - Build single-line `#gisUnifiedHeader`.
  - Compact `#gisSidebarPanel` (`360px`), slimmer mode banner, compact 4-tab bar (keeping exact badge HTML strings!), compact 1-row action bars in all 4 tabs.
  - Build `#gisFloatingMapDock` inside `#gisMapWrapper` at `top-3 right-3` holding Search, Basemap, and Unit circular buttons + left-opening popovers.
- [ ] **Step 5: Implement `assets/js/gis_master.js` updates**
  - Add centralized `setSidebarVisibility(visible)` helper using `gis-sidebar-collapsed` class and update all 4 callers (`openPointCreator`, `openPatternForm`, `openRouteForm`, `initSidebarToggle`).
  - Add `initFloatingMapControls()` handling single-open toggle, `aria-expanded`, auto-focus on search input, outside-click light-dismiss, `Escape` close + focus return to trigger, and `focusgroup` roving-tabindex fallback (`ArrowDown`/`ArrowUp`/`Home`/`End`).
  - Update `setGlobalMeasurementUnit()` to sync `#floatingUnitBadge`, set `aria-pressed`, and use crisp light+dark classes.
  - Update `setBasemapLayer()` to sync `aria-pressed`, update `#btnFloatingBasemapToggle` tooltip/title, and use crisp light+dark classes; close basemap popover after selection.
  - Update `updateDraggableLockUI()` to keep compact light+dark classes matching the simplified Tab Titik toolbar.
  - Update `openCaptureMode()` / `closeCaptureMode()` to close any open floating popover and toggle `#gisFloatingMapDock` visibility cleanly.
  - Call `initFloatingMapControls()` in `bootstrapApp()`.
- [ ] **Step 6: Rebuild Tailwind CSS & verify GREEN**
  Run: `npm run build:css && php -l views/layout/navbar.php views/gis.php && node --check assets/js/gis_master.js && DB_DATABASE=qrsar_test php tests/test_navigasi_renops_bra_dan_gis_ui.php`
  Expected: Build exit 0, syntax OK, ALL assertions PASS.

---

### Task 4: Full Verification, Live Browser E2E Check & Graphify Update

**Files:**
- Verify all existing + new tests, live UI on `http://127.0.0.1:8000`, and `graphify-out/`

- [ ] **Step 1: Run full JS unit tests & full PHP test suite**
  Run:
  - `for f in tests/js/*.test.js; do node "$f" || exit 1; done`
  - `DB_DATABASE=qrsar_test php tests/run_all.php`
  Expected: 7/7 JS test files PASS, 93/93 PHP test suites PASS (0 FAIL).
- [ ] **Step 2: Live Browser Verification (Visual + Functional E2E)**
  Use `browser` subagent on `http://127.0.0.1:8000`:
  - Verify GIS Plotting page (`/gis.php`): single unified header (~52px, zero double-header), Basarnas logo + GIS RADAR in header, sidebar tools spacious & compact buttons in all 4 tabs, 3 circular floating buttons at top-right (`Search`, `Basemap`, `Unit`), clicking each opens popover cleanly, switching unit updates circular badge, switching basemap updates map & closes popover, Capture Mode hides floating dock, Light & Dark modes crisp, 0 console errors.
  - Verify Navigation #1: open an operation's RENOPS draft → click Capture LKP → verify GIS opens with `from_renops` → click Capture & Simpan (or inspect existing snapshot banner button) → verify link has NO `target="_blank"` and navigates in the SAME tab directly back to that RENOPS draft!
  - Verify Navigation #2: open Detail Operasi → sub-tab BRA SAR → open a BRA document → click Back button → verify it returns directly to `/operasi_detail.php?id=<id>&tab=bra`!
- [ ] **Step 3: Update Knowledge Graph**
  Run: `graphify update .`
  Expected: Graph updated cleanly.
