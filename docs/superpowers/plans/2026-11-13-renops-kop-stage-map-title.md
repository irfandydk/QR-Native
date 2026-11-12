# RENOPS KOP, Cumulative Operation Stage, & Map Title Enhancement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengoptimalkan dokumen Rencana Operasi SAR (SMEAC) agar KOP menampilkan langsung judul operasi murni yang dapat diedit, Bagian D (Operation Stage) menampilkan riwayat kumulatif H1 s/d H7 dengan format terstruktur, serta judul operasi pada studio capture peta (LKP & Rencana Operasi) tampil utuh tanpa ID operasi.

**Architecture:** 
1. Menambahkan seksi `judul_operasi` ke dalam kontrak data `RenopsDokumenService` dan membungkus baris ke-3 KOP dengan atribut editable draft `data-sec="judul_operasi"` pada template SMEAC.
2. Membangun generator tahapan kumulatif di `RenopsDokumenService` yang merajut pengerahan awal dan perkembangan harian dari seluruh hari pendahulu (H1 s/d HN-1) secara kronologis berlabel hari.
3. Menghapus prefix `OPS #[ID] - ` dan limit kaku 62 karakter pada fungsi kanvas HUD Leaflet `assets/js/gis_master.js`, menggantikannya dengan rendering adaptif penuh.

**Tech Stack:** PHP 8, JavaScript (ES6+ HTML5 Canvas), MariaDB/MySQL.

**Spec:** `docs/superpowers/specs/2026-11-13-renops-kop-stage-map-title-design.md`

## Global Constraints
- Tidak menambahkan "OPERASI SAR" dan tidak menduplikasi `jenis_kejadian` pada baris ke-3 KOP (cukup langsung judul operasi saja).
- Memastikan baris judul pada KOP dokumen tetap dapat disunting langsung pada draf (`renops_dokumen.php`) dan tersimpan ke basis data.
- Menjaga seluruh pengujian regresi `tests/run_all.php` tetap lulus 100%.

## Review Focus
1. KOP tanpa nama operasi: Jika `nama_operasi` kosong, fallback yang aman tidak menghasilkan error atau baris kosong yang merusak tata letak KOP.
2. KOP yang disunting draf: Suntingan pengguna pada baris ke-3 KOP harus tetap dipertahankan saat dokumen disimpan dan diterbitkan.
3. Operasi Hari ke-1 (H1): Bagian D tetap ringkas menampilkan pengerahan awal H1 tanpa label perulangan yang aneh.
4. Operasi Hari ke-2 dan seterusnya tanpa lapbang pendahulu: Jika hari sebelumnya belum memiliki input LAPBANG, sistem tetap menampilkan riwayat pelaksanaan hari sebelumnya secara elegan tanpa melempar warning PHP.
5. Judul peta sangat panjang: Memastikan judul operasi hingga 150+ karakter pada kanvas peta 16:9 tidak menimpa kompas di pojok kanan atas.

---

### Task 1: KOP Dokumen RENOPS (Judul Murni & Editable Section)

**Files:**
- Modify: `services/RenopsDokumenService.php:218-241`
- Modify: `views/operasi/partials/renops_smeac_doc.php:30-40, 315-325`
- Test: `tests/test_renops_kop_judul_editable.php`

**Interfaces:**
- Consumes: `$meta['nama_operasi']`, `$doc['sections']['judul_operasi']`.
- Produces: `judul_operasi` section in `RenopsDokumenService` and `data-sec="judul_operasi"` in `renops_smeac_doc.php`.

- [x] **Step 1: Write the failing test**

Buat berkas tes `tests/test_renops_kop_judul_editable.php`:

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

echo "=== TEST RENOPS KOP JUDUL EDITABLE ===\n";

$idOps = OperasiModel::create([
    'nama_operasi' => 'KMM 1 ORANG TENGGELAM DI TELUK KENDARI',
    'jenis_kejadian' => 'Kecelakaan Kapal',
]);
$idRenops = RenopsModel::create([
    'id_operasi' => $idOps,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-11-13',
    'srus' => [],
]);

$doc = RenopsDokumenService::resolve($idRenops);
assertCondition(isset($doc['sections']['judul_operasi']), 'Kontrak data dokumen memuat seksi judul_operasi');
assertCondition($doc['sections']['judul_operasi'] === 'KMM 1 ORANG TENGGELAM DI TELUK KENDARI', 'Default judul_operasi langsung nama operasi murni tanpa jenis_kejadian');

// Simpan suntingan draf
RenopsDokumenModel::saveDraft($idRenops, ['sections' => ['judul_operasi' => 'JUDUL HASIL SUNTINGAN MANUAL USER']]);
$docEdited = RenopsDokumenService::resolve($idRenops);
assertCondition($docEdited['sections']['judul_operasi'] === 'JUDUL HASIL SUNTINGAN MANUAL USER', 'Suntingan manual judul_operasi tersimpan pada draf');

$view = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/renops_smeac_doc.php');
assertCondition(str_contains($view, 'data-sec="judul_operasi"') || str_contains($view, "\$sec('judul_operasi'"), 'KOP baris 3 memiliki penanda data-sec editable');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
```

- [x] **Step 2: Run test to verify it fails**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_kop_judul_editable.php`  
Expected: FAIL

- [x] **Step 3: Implement `judul_operasi` in `services/RenopsDokumenService.php`**

- Tambahkan `'judul_operasi' => $nama` di `defaultSections()`.
- Pastikan `resolve()` memuat suntingan `judul_operasi` dari draf.

- [x] **Step 4: Implement in `views/operasi/partials/renops_smeac_doc.php`**

- Ambil `$kopBaris3 = trim((string)($S['judul_operasi'] ?? $namaOps));` (jika kosong, fallback ke `$namaOps`).
- Bungkus `<p class="k3 sec"<?= $sec('judul_operasi', 'text') ?>><span class="sec-body"><?= e($kopBaris3) ?></span></p>`.

- [x] **Step 5: Run test to verify it passes**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_kop_judul_editable.php`  
Expected: PASS (4/4 assertions)

- [x] **Step 6: Commit**

```bash
git add services/RenopsDokumenService.php views/operasi/partials/renops_smeac_doc.php tests/test_renops_kop_judul_editable.php
git commit -m "feat(renops): make kop baris 3 display pure operation title with inline editing support"
```

---

### Task 2: D. Operation Stage Kumulatif (Hari ke-1 s/d Hari ke-7)

**Files:**
- Modify: `services/RenopsDokumenService.php:205-216`
- Modify: `tests/test_renops_dokumen_resolve.php:155`
- Test: `tests/test_renops_cumulative_operation_stage.php`

**Interfaces:**
- Consumes: `$ops`, `$renops`, `$sumber`, `$n`, `$label`, `$ops['laporan_harian']`.
- Produces: `operation_stage` multi-day cumulative chronological string in `$sections['operation_stage']`.

- [x] **Step 1: Write the failing test**

Buat berkas tes `tests/test_renops_cumulative_operation_stage.php`:

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

echo "=== TEST RENOPS CUMULATIVE OPERATION STAGE ===\n";

$idOps = OperasiModel::create([
    'nama_operasi' => 'Operasi SAR Uji Kumulatif',
    'waktu_berangkat' => '2026-11-10 07:00:00',
    'tim_berangkat' => 'Tim Alpha',
    'detail_unsur' => [['nama_unsur' => 'Basarnas', 'jumlah_personil' => 6]],
]);

// H1
$idR1 = RenopsModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-11-10', 'srus' => [],
]);
$doc1 = RenopsDokumenService::resolve($idR1);
$stage1 = $doc1['sections']['operation_stage'];
assertCondition(str_contains($stage1, 'diberangkatkan 6 (enam) orang personel Tim Alpha'), 'H1: memuat pengerahan awal');

// Lapbang H1
LaporanModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.1', 'waktu_laporan' => '2026-11-10 20:00:00',
    'isi_laporan' => 'Hasil H1: Pencarian seluas 10 NM2 nihil korban.',
]);

// H2
$idR2 = RenopsModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-11-11',
    'waktu_mulai_ops' => '2026-11-11 07:00:00', 'srus' => [],
]);
$doc2 = RenopsDokumenService::resolve($idR2);
$stage2 = $doc2['sections']['operation_stage'];
assertCondition(str_contains($stage2, 'Hari ke-1:') && str_contains($stage2, 'Hasil H1: Pencarian seluas 10 NM2 nihil korban.'), 'H2: memuat rekapitulasi Hari ke-1 beserta hasil lapbang H1');
assertCondition(str_contains($stage2, 'Hari ke-2 (Rencana Pelaksanaan):') && str_contains($stage2, 'Operasi SAR Hari ke-2 dilanjutkan'), 'H2: memuat pelaksanaan Hari ke-2');

// Lapbang H2
LaporanModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.2', 'waktu_laporan' => '2026-11-11 20:00:00',
    'isi_laporan' => 'Hasil H2: Ditemukan serpihan perahu.',
]);

// H3
$idR3 = RenopsModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.3', 'tanggal_laporan' => '2026-11-12',
    'waktu_mulai_ops' => '2026-11-12 07:00:00', 'srus' => [],
]);
$doc3 = RenopsDokumenService::resolve($idR3);
$stage3 = $doc3['sections']['operation_stage'];
assertCondition(str_contains($stage3, 'Hari ke-1:') && str_contains($stage3, 'Hari ke-2:') && str_contains($stage3, 'Hari ke-3 (Rencana Pelaksanaan):'), 'H3: memuat kumulatif Hari ke-1, Hari ke-2, dan Hari ke-3');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
```

- [x] **Step 2: Run test to verify it fails**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_cumulative_operation_stage.php`  
Expected: FAIL

- [x] **Step 3: Implement cumulative operation stage in `services/RenopsDokumenService.php`**

- Buat method `buildOperationStage(array $ops, array $renops, int $n, string $label, int $personel)`:
  - Bangun teks pengerahan awal Hari ke-1.
  - Kumpulkan laporan harian per hari $i$ dari 1 s/d $n-1$.
  - Format hari demi hari menggunakan Pilihan A:
    `Hari ke-1:` ...
    `Hari ke-2:` ...
    `Hari ke-{$label} (Rencana Pelaksanaan):` ...
  - Kembalikan string gabungan dengan pemisah `\n\n`.

- [x] **Step 4: Update `tests/test_renops_dokumen_resolve.php` line 155 for the new format**

- Sesuaikan asersi tes agar mencocokkan format kumulatif yang baru.

- [x] **Step 5: Run tests to verify they pass**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_cumulative_operation_stage.php`  
Expected: PASS (4/4 assertions)

Run: `DB_DATABASE=qrsar_test php tests/test_renops_dokumen_resolve.php`  
Expected: PASS

- [x] **Step 6: Commit**

```bash
git add services/RenopsDokumenService.php tests/test_renops_cumulative_operation_stage.php tests/test_renops_dokumen_resolve.php
git commit -m "feat(renops): implement cumulative multi-day operation stage from day 1 to day 7"
```

---

### Task 3: Judul Operasi Lengkap Tanpa ID pada Peta LKP & Rencana Operasi

**Files:**
- Modify: `assets/js/gis_master.js:8075-8120`
- Test: `tests/test_gis_map_capture_title.php`

**Interfaces:**
- Consumes: `opObj.nama_operasi` in `drawCaptureHudOverlay`.
- Produces: Complete title without `OPS #` and without 62-char cutoff in `assets/js/gis_master.js`.

- [x] **Step 1: Write the failing test**

Buat berkas tes `tests/test_gis_map_capture_title.php`:

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

echo "=== TEST GIS MAP CAPTURE TITLE ===\n";

$js = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');

assertCondition(!str_contains($js, '`OPS #${opObj.id_operasi} - ${opObj.nama_operasi}`'), 'GIS HUD tidak lagi menyertakan prefix OPS #ID pada judul peta');
assertCondition(!str_contains($js, 'substring(0, 62)'), 'GIS HUD tidak memotong paksa judul pada 62 karakter');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
```

- [x] **Step 2: Run test to verify it fails**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_map_capture_title.php`  
Expected: FAIL (0 of 2 assertions pass)

- [x] **Step 3: Implement in `assets/js/gis_master.js`**

- Di `drawCaptureHudOverlay(ctx, w, h)`:
  - Ubah `opTitle = opObj ? (opObj.nama_operasi || 'OPERASI SAR') : 'PETA TAKTIS OPERASI SAR';`
  - Hapus pemotongan `.substring(0, 62)`.
  - Hitung lebar teks judul dengan `ctx.measureText(opTitle).width`.
  - Buat lebar kotak HUD dinamis (rentang 540 s/d `w - 90`). Jika judul sangat panjang, bungkus menjadi 2 baris atau kecilkan ukuran font proporsional agar tampil utuh 100%.

- [x] **Step 4: Run test to verify it passes**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_map_capture_title.php`  
Expected: PASS (2/2 assertions)

- [x] **Step 5: Commit**

```bash
git add assets/js/gis_master.js tests/test_gis_map_capture_title.php
git commit -m "feat(gis): display full operation title without operation id on map capture hud"
```

---

### Task 4: Full End-to-End Regression Verification

**Files:**
- Verify: `tests/run_all.php`
- Verify: `tests/js/*.test.js`

- [x] **Step 1: Run Node.js unit tests**

Run: `node --test tests/js/*.test.js`  
Expected: PASS (6/6 test files pass)

- [x] **Step 2: Run all newly created tests**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_kop_judul_editable.php && DB_DATABASE=qrsar_test php tests/test_renops_cumulative_operation_stage.php && DB_DATABASE=qrsar_test php tests/test_gis_map_capture_title.php`  
Expected: ALL PASS

- [x] **Step 3: Run full automated regression suite**

Run: `DB_DATABASE=qrsar_test php tests/run_all.php`  
Expected: ALL PASS (84/84 or 87/87 suites pass 100%)

