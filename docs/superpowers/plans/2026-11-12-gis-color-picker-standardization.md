# Standarisasi Color Picker GIS (Search Area & Pola SAR) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyamakan sistem pemilih warna (*color picker*) dan palet preset pada Tab Search Area dan Tab Pola SAR agar seragam dengan standar interaktif Tab Jarak & ETA (Rute Navigasi).

**Architecture:** Menerapkan komponen swatch preset 8 warna maritim taktis berindikator cincin aktif, baris khusus pemilih warna kustom (`<input type="color">`), dan label kode HEX dinamis dengan *two-way reactive binding* langsung ke layer kanvas Leaflet (`updateLivePolygonVisual` dan `updateActivePatternStyle`).

**Tech Stack:** PHP 8, Tailwind CSS, Vanilla JavaScript (ES6+), Leaflet.js, MariaDB/MySQL.

**Spec:** `docs/superpowers/specs/2026-11-12-gis-color-picker-standardization-design.md`

## Global Constraints
- Tetap menjaga pemisahan independen antara Warna Garis Border (`polyStrokeColor`) dan Warna Isi Arsiran (`polyFillColor`) pada Search Area.
- Mempertahankan ID elemen pengujian eksisting: `#polyFillColorSwatches`, `#polyStrokeOpacitySlider`, `#patternLineColor`, dan `#patternLineOpacity`.
- Tidak mengubah skema tabel database `GisPolygon` dan `GisSearchPattern`.
- Menjaga seluruh 82 test suite di `tests/run_all.php` tetap lulus 100%.

## Review Focus
1. Sinkronisasi warna swatch ke custom input color: Memastikan saat pengguna mengklik salah satu dari 8 preset swatch, nilai `<input type="color">` dan label HEX langsung terupdate.
2. Sinkronisasi custom input color ke swatch: Memastikan saat pengguna menggeser pemilih warna bebas, ring aktif swatch preset dilepas jika warna tidak cocok dengan 8 palet.
3. Update visual Leaflet live: Memastikan warna garis dan arsiran poligon/lingkaran/persegi langsung berubah pada kanvas Leaflet tanpa perlu menyimpan terlebih dahulu.
4. Mode edit poligon: Memastikan saat area yang telah tersimpan dibuka kembali untuk diedit (`startPolygonDrawing`), warna garis dan arsiran kustom dipulihkan ke picker dan teks HEX.
5. Mode edit pola pencarian: Memastikan saat pola IAMSAR yang tersimpan diedit (`editPattern`), warna garis dan nilai HEX dipulihkan ke picker dan swatch yang sesuai.

---

### Task 1: Search Area (Tab 2) Color Picker & Two-Way Sync

**Files:**
- Modify: `views/gis.php:805-860`
- Modify: `assets/js/gis_master.js:2790-2830, 3045-3075, 3255-3290`
- Test: `tests/test_gis_area_color_picker.php`

**Interfaces:**
- Consumes: `polyStrokeColor`, `polyFillColor`, `updateLivePolygonVisual()` in `assets/js/gis_master.js`.
- Produces: `#polyCustomStrokeColorInput`, `#polyCustomStrokeColorHex`, `#polyCustomFillColorInput`, `#polyCustomFillColorHex` in `views/gis.php`.

- [x] **Step 1: Write the failing test**

Buat berkas tes `tests/test_gis_area_color_picker.php` untuk memverifikasi keberadaan input warna kustom garis dan isi, label HEX, serta fungsi sinkronisasi di JS:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$testTotal = 0; $testPassed = 0;
function assertCondition(bool $cond, string $msg): void {
    global $testTotal, $testPassed;
    $testTotal++;
    if ($cond) { $testPassed++; echo " [PASS] {$msg}\n"; }
    else { echo " [FAIL] {$msg}\n"; }
}

echo "=== TEST GIS AREA COLOR PICKER ===\n";
$view = (string)file_get_contents(ROOT_DIR . '/views/gis.php');
$js = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');

assertCondition(str_contains($view, 'id="polyCustomStrokeColorInput"') && str_contains($view, 'id="polyCustomStrokeColorHex"'), 'View memiliki custom stroke color picker & HEX label');
assertCondition(str_contains($view, 'id="polyCustomFillColorInput"') && str_contains($view, 'id="polyCustomFillColorHex"'), 'View memiliki custom fill color picker & HEX label');
assertCondition(str_contains($js, 'polyCustomStrokeColorInput') && str_contains($js, 'polyCustomFillColorInput'), 'JS mengikat event listener pada custom color pickers Search Area');
assertCondition(str_contains($js, "setText('polyCustomStrokeColorHex'") || str_contains($js, "polyCustomStrokeColorHex.textContent ="), 'JS menyinkronkan label HEX stroke & fill');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
```

- [x] **Step 2: Run test to verify it fails**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_area_color_picker.php`  
Expected: FAIL (0 of 4 assertions pass)

- [x] **Step 3: Update `views/gis.php` for Search Area Color Styling**

Tambahkan baris warna kustom di bawah swatch garis (`#polyStrokeColorSwatches`) dan di bawah swatch arsiran (`#polyFillColorSwatches`) lengkap dengan input warna dan label HEX.

- [x] **Step 4: Update `assets/js/gis_master.js` for Search Area Two-Way Sync**

- Tambahkan binding event listener `input` pada `#polyCustomStrokeColorInput` dan `#polyCustomFillColorInput` di `initPolygonUI()`.
- Perbarui `syncPolyStyleControls()` untuk mengatur nilai input kustom dan teks HEX.
- Panggil `updateLivePolygonVisual()` saat warna kustom berubah.

- [x] **Step 5: Run tests to verify they pass**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_area_color_picker.php`  
Expected: PASS (4/4 assertions)

Run: `DB_DATABASE=qrsar_test php tests/test_gis_area_style.php`  
Expected: PASS (11/11 assertions)

- [x] **Step 6: Commit**

```bash
git add views/gis.php assets/js/gis_master.js tests/test_gis_area_color_picker.php
git commit -m "feat(gis): add custom color picker and hex display for search area stroke and fill"
```

---

### Task 2: Pola SAR (Tab 3) Color Picker & Two-Way Sync

**Files:**
- Modify: `views/gis.php:1040-1080`
- Modify: `assets/js/gis_master.js:3960-4030, 4820-4840, 5040-5060, 5325-5345`
- Test: `tests/test_gis_pattern_color_picker.php`

**Interfaces:**
- Consumes: `#patternLineColor`, `updateActivePatternStyle()`, `editPattern()` in `assets/js/gis_master.js`.
- Produces: `#labelPatternLineColor`, `#patternColorSwatches`, `#patternCustomColorHex` in `views/gis.php`.

- [x] **Step 1: Write the failing test**

Buat berkas tes `tests/test_gis_pattern_color_picker.php` untuk memverifikasi standarisasi palet dan pemilih warna pada Pola SAR:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$testTotal = 0; $testPassed = 0;
function assertCondition(bool $cond, string $msg): void {
    global $testTotal, $testPassed;
    $testTotal++;
    if ($cond) { $testPassed++; echo " [PASS] {$msg}\n"; }
    else { echo " [FAIL] {$msg}\n"; }
}

echo "=== TEST GIS PATTERN COLOR PICKER ===\n";
$view = (string)file_get_contents(ROOT_DIR . '/views/gis.php');
$js = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');

assertCondition(str_contains($view, 'id="labelPatternLineColor"'), 'View memiliki label active HEX untuk garis Pola SAR');
assertCondition(str_contains($view, 'id="patternColorSwatches"'), 'View memiliki kontainer swatches 8 warna Pola SAR');
assertCondition(str_contains($view, 'id="patternCustomColorHex"'), 'View memiliki label kode HEX kustom Pola SAR');
assertCondition(str_contains($js, 'patternColorSwatches') || str_contains($js, 'syncPatternStyleControls'), 'JS menginisialisasi atau menyinkronkan swatch Pola SAR');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
```

- [x] **Step 2: Run test to verify it fails**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_pattern_color_picker.php`  
Expected: FAIL (0 of 4 assertions pass)

- [x] **Step 3: Update `views/gis.php` for Pola SAR Color Styling**

Tata ulang blok kustomisasi garis pola di `views/gis.php`:
- Header dengan `#labelPatternLineColor`.
- Kontainer palet 8 swatch `#patternColorSwatches`.
- Baris "Warna Kustom:" dengan `#patternLineColor` dan `#patternCustomColorHex`.

- [x] **Step 4: Update `assets/js/gis_master.js` for Pola SAR Two-Way Sync**

- Buat fungsi `syncPatternStyleControls()` dan `initPatternStyleControls()`.
- Hubungkan event listener `click` pada swatch dot dan `input` pada `#patternLineColor`.
- Perbarui `updateActivePatternStyle()` agar menyinkronkan tampilan label HEX dan seleksi cincin swatch.
- Perbarui `editPattern()` untuk memanggil `syncPatternStyleControls()`.

- [x] **Step 5: Run tests to verify they pass**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_pattern_color_picker.php`  
Expected: PASS (4/4 assertions)

Run: `DB_DATABASE=qrsar_test php tests/test_gis_consistency_and_esar_arrows.php`  
Expected: PASS (30/30 assertions)

- [x] **Step 6: Commit**

```bash
git add views/gis.php assets/js/gis_master.js tests/test_gis_pattern_color_picker.php
git commit -m "feat(gis): standardize search pattern color swatches and custom color picker"
```

---

### Task 3: Full End-to-End Regression & Verification

**Files:**
- Verify: `tests/run_all.php`
- Verify: `tests/js/*.test.js`

- [x] **Step 1: Run Node.js unit tests**

Run: `node --test tests/js/*.test.js`  
Expected: PASS (6/6 test files pass)

- [x] **Step 2: Run GIS-specific test suites**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_area_color_picker.php && DB_DATABASE=qrsar_test php tests/test_gis_pattern_color_picker.php && DB_DATABASE=qrsar_test php tests/test_gis_area_style.php && DB_DATABASE=qrsar_test php tests/test_audit_modul_6_gis_plotting.php`  
Expected: ALL PASS

- [x] **Step 3: Run full automated regression suite**

Run: `DB_DATABASE=qrsar_test php tests/run_all.php`  
Expected: ALL PASS (84/84 suites pass 100%)

- [x] **Step 4: Commit any remaining test updates**

```bash
git add tests/
git commit -m "test(gis): ensure full regression suite passes with new color picker controls"
```

