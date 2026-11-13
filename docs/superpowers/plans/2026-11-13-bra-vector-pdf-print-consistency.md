# BRA SAR Vector PDF Export & Print Consistency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan fitur Unduh PDF resmi berbasis vektor (*searchable text*, bukan gambar) pada Berita Radio SAR (BRA) yang 100% konsisten dengan dialog Cetak PDF (`window.print()`), menggunakan engine native Google Chrome Headless server-side yang sama dengan RENOPS.

**Architecture:** Membuat service terisolasi `BraPdfService` yang mengonversi HTML lembar telegram BRA menjadi berkas PDF vektor (%PDF-) melalui Chrome Headless `--print-to-pdf`, menyediakan endpoint `?format=pdf` pada `BraController::showDocument()`, dan memperbarui tombol di `views/bra/view.php` menjadi tombol resmi *"Unduh PDF"* yang terintegrasi dengan `pdf_document_exporter.js`.

**Tech Stack:** PHP 8+, Google Chrome / Chromium Headless CLI (`--print-to-pdf`), Tailwind CSS, Vanilla JavaScript, DOM API, PHPUnit-like Test Runner (`tests/run_all.php`).

**Spec:** [`docs/superpowers/specs/2026-11-13-bra-vector-pdf-print-consistency-design.md`](file:///Users/irfandydk/antigravity/QR-SAR/docs/superpowers/specs/2026-11-13-bra-vector-pdf-print-consistency-design.md)

---

## Global Constraints

- Teks pada berkas PDF hasil unduhan harus 100% vektor murni dan dapat dicari (*searchable / selectable* via Ctrl+F). Tidak boleh menggunakan screenshot/raster JPEG canvas.
- Tampilan berkas hasil *"Unduh PDF"* dan dialog *"Cetak / Simpan PDF"* (`window.print()`) harus identik dan rapi mengikuti CSS `@media print` lembar telegram Basarnas.
- Otorisasi dokumen harus dijaga ketat: akses internal membutuhkan autentikasi sesi, dan akses publik mewajibkan tanda tangan digital/token valid. Token tidak valid atau dokumen tidak ditemukan harus menghasilkan respon 404/invalid tanpa membocorkan konten.
- Seluruh 87 test suite PHP yang ada ditambah test suite baru (total 88 test suite) dan 6 test suite JS harus lulus 100% tanpa regresi.

---

## Review Focus

1. **Akses Publik Tanpa Token atau Token Salah:** Request `?view=1&format=pdf` oleh tamu tanpa token atau dengan token salah harus ditolak (HTTP 404 / verify invalid), bukan mengunduh PDF.
2. **Karakter Khusus pada Nomor BRA:** Nomor BRA yang memiliki garis miring atau karakter non-alphanumeric (misal `BRA/01/KPP-KDI/XI/2026`) harus disanitasi menjadi nama file aman `BRA-SAR-BRA_01_KPP-KDI_XI_2026.pdf`.
3. **Ketersediaan Chrome Headless di Server:** Jika biner Chrome tidak ditemukan atau gagal menghasilkan PDF, endpoint harus mengembalikan status HTTP 503 JSON dan frontend harus fallback secara cerdas ke dialog cetak vektor bawaan browser (`window.print()`).
4. **Isolasi Elemen Non-Cetak (Zero-Leakage):** Toolbar atas, tombol aksi, banner sertifikat verifikasi digital publik, dan latar gelap tidak boleh bocor ke dalam lembar PDF cetak.
5. **Pemetaan Aset Lokal (`file://`):** Seluruh logo Basarnas, stylesheet, dan font lokal harus dimuat langsung dari disk lokal saat Chrome Headless merender berkas HTML sementara tanpa bergantung pada sesi jaringan HTTP.

---

### Task 1: Buat Backend Service `BraPdfService` dan Test Suite Awal

**Files:**
- Create: `services/BraPdfService.php`
- Create: `tests/test_bra_pdf_export.php`

**Interfaces:**
- Produces:
  - `BraPdfService::findChromeBinary(): ?string`
  - `BraPdfService::prepareHtmlForLocalRender(string $html): string`
  - `BraPdfService::renderHtmlToPdf(string $html): ?string`

- [ ] **Step 1: Write the failing test in `tests/test_bra_pdf_export.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

echo "=== TEST BRA PDF VECTOR EXPORT & PRINT CONSISTENCY ===\n";

// 1. Verifikasi ketersediaan kelas BraPdfService
assertCondition(class_exists('BraPdfService'), 'Class BraPdfService harus tersedia');

// 2. Verifikasi findChromeBinary mendeteksi biner Chrome yang valid
$chromeBin = BraPdfService::findChromeBinary();
assertCondition($chromeBin !== null && is_executable($chromeBin), 'BraPdfService::findChromeBinary menemukan biner Chrome yang dapat dieksekusi');

// 3. Verifikasi prepareHtmlForLocalRender mengubah path lokal menjadi file://
$dummyHtml = '<html><head></head><body><img src="' . BASE_URL . '/assets/images/logo_basarnas.svg"><div class="print:hidden">Toolbar</div><div id="official-bra-document">Isi Dokumen BRA</div></body></html>';
$prepared = BraPdfService::prepareHtmlForLocalRender($dummyHtml);
assertCondition(str_contains($prepared, 'file://'), 'BraPdfService::prepareHtmlForLocalRender mengubah tautan aset menjadi file:// lokal');
assertCondition(str_contains($prepared, 'print\\:hidden') || str_contains($prepared, 'display: none'), 'BraPdfService::prepareHtmlForLocalRender menginjeksi aturan print override');

// 4. Verifikasi renderHtmlToPdf menghasilkan biner %PDF-
$pdfBytes = BraPdfService::renderHtmlToPdf($dummyHtml);
assertCondition(is_string($pdfBytes) && str_starts_with($pdfBytes, '%PDF-') && strlen($pdfBytes) > 500, 'BraPdfService::renderHtmlToPdf menghasilkan biner PDF vektor valid via Chrome Headless');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `DB_DATABASE=qrsar_test php tests/test_bra_pdf_export.php`  
Expected: FAIL (Class `BraPdfService` not found)

- [ ] **Step 3: Implement `BraPdfService` in `services/BraPdfService.php`**

Implementasikan:
- `findChromeBinary(): ?string`: Mendeteksi `CHROME_BIN` dari env, kandidat macOS (`/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`, `/Applications/Chromium.app/...`), dan kandidat Linux (`/usr/bin/google-chrome-stable`, `/usr/bin/google-chrome`, `/usr/bin/chromium`, dll).
- `prepareHtmlForLocalRender(string $html): string`:
  - Mengonversi `/assets/...` dan `/uploads/...` menjadi URI `file://` absolut.
  - Menginjeksi CSS print override di dalam `<head>`:
    ```css
    <style>
    html, body { background: #ffffff !important; color: #000000 !important; }
    .print\:hidden, [class*="print:hidden"], .no-print, header, footer, .navbar, .sidebar { display: none !important; }
    #official-bra-document, .bra-official-paper { box-shadow: none !important; border: none !important; margin: 0 auto !important; width: 100% !important; max-width: 180mm !important; }
    </style>
    ```
- `renderHtmlToPdf(string $html): ?string`:
  - Menulis `$preparedHtml` ke file temporer dengan izin aman `0600`.
  - Menjalankan command Chrome Headless `--headless --disable-gpu --no-sandbox --disable-dev-shm-usage --no-pdf-header-footer --virtual-time-budget=1200 --print-to-pdf=$tmpPdf $fileUrl`.
  - Membaca biner `%PDF-`, memastikan ukuran > 500 byte, dan membersihkan file temporer di blok `finally`.

- [ ] **Step 4: Run test to verify Task 1 passes**

Run: `DB_DATABASE=qrsar_test php tests/test_bra_pdf_export.php`  
Expected: PASS untuk seluruh 4 asersi awal.

- [ ] **Step 5: Commit Task 1**

```bash
git add services/BraPdfService.php tests/test_bra_pdf_export.php
git commit -m "feat(bra): implement BraPdfService for Chrome Headless vector PDF generation"
```

---

### Task 2: Implementasikan Endpoint `?format=pdf` pada `BraController`

**Files:**
- Modify: `controllers/BraController.php:58-155`
- Modify: `tests/test_bra_pdf_export.php`

**Interfaces:**
- Consumes: `BraPdfService::renderHtmlToPdf(string $html): ?string`
- Produces: HTTP endpoint `GET /bra.php?view={id}&format=pdf` & `GET /bra.php?view={id}&token={token}&format=pdf` yang mengalirkan file `application/pdf`.

- [ ] **Step 1: Expand `tests/test_bra_pdf_export.php` with controller endpoint assertions**

Tambahkan uji interaksi HTTP pada `tests/test_bra_pdf_export.php`:
1. Buat data uji BRA baru melalui `BraModel::create(...)`.
2. Uji akses login internal dengan `$_GET['view'] = $id` dan `$_GET['format'] = 'pdf'`. Pastikan mengembalikan status HTTP 200, Content-Type `application/pdf`, biner berawalan `%PDF-`, dan header Content-Disposition memuat nama berkas `BRA-SAR-`.
3. Uji akses publik bertanda tangan digital dengan `token` valid: pastikan mengembalikan status HTTP 200 dan biner PDF.
4. Uji akses publik tanpa login dan tanpa token: pastikan ditolak dengan status HTTP 404 / redirect login.
5. Uji akses dengan token yang salah/dimanipulasi: pastikan ditolak dengan status HTTP 404.

- [ ] **Step 2: Run test to verify it fails**

Run: `DB_DATABASE=qrsar_test php tests/test_bra_pdf_export.php`  
Expected: FAIL (Endpoint belum menangani `format=pdf` atau merender view HTML biasa).

- [ ] **Step 3: Implement handler `format=pdf` in `BraController::showDocument()`**

Di dalam `BraController::showDocument(mixed $viewId)`:
1. Setelah verifikasi dokumen dan otorisasi berhasil (dokumen valid dan token/sesi sah):
2. Periksa apakah `($_GET['format'] ?? '') === 'pdf'`:
   - Tangkap output render HTML lembar BRA menggunakan `ob_start()`:
     ```php
     ob_start();
     $this->render('bra/view', [
         'bra' => $bra,
         'verificationStatus' => $verificationStatus,
         'verificationMessage' => $verificationMessage,
         'pageTitle' => $pageTitle,
         'currentRoute' => $currentRoute,
         'navbarTitle' => $navbarTitle,
         'isGuest' => $isGuest,
         'docId' => $docId
     ], [
         'layout' => 'public',
         'title' => 'Dokumen Berita Radio SAR'
     ]);
     $html = (string)ob_get_clean();
     ```
   - Panggil `BraPdfService::renderHtmlToPdf($html)`.
   - Jika biner PDF berhasil dibuat (`$pdfBytes !== null`):
     - Sanitasi nama berkas:
       ```php
       $rawNo = (string)($bra['no_bra'] ?? 'BRA_SAR');
       $safeNo = preg_replace('/[^a-zA-Z0-9_\-.]+/', '_', $rawNo);
       $safeName = 'BRA-SAR-' . trim((string)$safeNo, '_');
       ```
     - Kirim header:
       ```php
       if (!headers_sent()) {
           header('Content-Type: application/pdf');
           header('Content-Disposition: attachment; filename="' . $safeName . '.pdf"');
           header('Content-Length: ' . strlen($pdfBytes));
           header('Cache-Control: no-store, no-cache, must-revalidate');
       }
       echo $pdfBytes;
       exit;
       ```
   - Jika gagal/Chrome tidak tersedia:
     - Kembalikan HTTP 503 dengan JSON: `{"success": false, "message": "ENGINE_UNAVAILABLE"}`.

- [ ] **Step 4: Run test to verify Task 2 passes**

Run: `DB_DATABASE=qrsar_test php tests/test_bra_pdf_export.php`  
Expected: PASS untuk seluruh asersi endpoint controller.

- [ ] **Step 5: Commit Task 2**

```bash
git add controllers/BraController.php tests/test_bra_pdf_export.php
git commit -m "feat(bra): support ?format=pdf in BraController for vector PDF download"
```

---

### Task 3: Integrasi Frontend pada `views/bra/view.php` & `assets/js/pdf_document_exporter.js`

**Files:**
- Modify: `assets/js/pdf_document_exporter.js:275-285`
- Modify: `views/bra/view.php:38-44, 105-115, 170-175, 445-455, 670-755`
- Modify: `tests/test_bra_pdf_export.php`

**Interfaces:**
- Consumes:
  - `window.downloadOfficialPdf(customFilename, buttonEl)`
  - URL `bra.php?view={id}&format=pdf` (atau dengan `&token={token}`)
- Produces: UI tombol *"Unduh PDF"* resmi yang mengunduh PDF vektor tanpa screenshot gambar.

- [ ] **Step 1: Write tests verifying frontend markup and JS integration in `tests/test_bra_pdf_export.php`**

Tambahkan pengujian:
1. Render HTML `bra/view` via controller.
2. Pastikan HTML memuat atribut `id="btnDownloadPdf"` dengan `data-pdf-url` dan `data-pdf-filename`.
3. Pastikan HTML memuat teks tombol `Unduh PDF` dan memanggil `downloadOfficialPdf(null, this)`.
4. Pastikan teks tombol lama `"PDF Gambar"` dan fungsi `downloadBraDocumentPdf` tidak lagi digunakan.
5. Pastikan `<script src="...pdf_document_exporter.js"></script>` disertakan.
6. Pastikan dokumen target memiliki class `page-sheet` atau selektor ID `#official-bra-document`.

- [ ] **Step 2: Update `assets/js/pdf_document_exporter.js`**

Di baris 277 `assets/js/pdf_document_exporter.js`:
Perluas pencarian elemen lembar dokumen agar mendukung `#official-bra-document`:
```javascript
const sheet = document.querySelector('.page-sheet') || document.querySelector('#official-bra-document') || document.querySelector('.bra-official-paper');
```

- [ ] **Step 3: Update `views/bra/view.php`**

1. Siapkan URL unduh PDF vektor dan nama berkas bersih di bagian atas view:
   ```php
   $cleanNoBra = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)($bra['no_bra'] ?? 'BRA_SAR'));
   $pdfFilename = 'BRA-SAR-' . trim((string)$cleanNoBra, '_') . '.pdf';
   $pdfDownloadUrl = BASE_URL . '/bra.php?view=' . (int)$bra['id_bra']
       . (!empty($_GET['token']) ? '&token=' . urlencode((string)$_GET['token']) : ($isGuest ? '&token=' . urlencode($docHash) : ''))
       . '&format=pdf';
   ```
2. Tambahkan class `page-sheet bra-official-paper` pada container `#official-bra-document`:
   ```html
   <div id="official-bra-document" class="page-sheet bra-official-paper w-full bg-white text-black p-0 border-0 shadow-none font-sans print:p-0">
   ```
3. Ganti tombol lama `"PDF Gambar"` dengan tombol resmi `"Unduh PDF"`:
   ```html
   <button type="button" id="btnDownloadPdf" 
           data-pdf-filename="<?= e($pdfFilename) ?>" 
           data-pdf-url="<?= e($pdfDownloadUrl) ?>" 
           onclick="downloadOfficialPdf(null, this)" 
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-md transition cursor-pointer" 
           title="Unduh berkas PDF resmi (teks tajam &amp; dapat dicari / searchable, identik dengan hasil cetak)">
     <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
     <span>Unduh PDF</span>
   </button>
   ```
4. Pastikan script exporter dimuat:
   ```html
   <script src="<?= assetUrl('assets/js/pdf_document_exporter.js') ?>"></script>
   ```
5. Bersihkan fungsi legacy `window.downloadBraDocumentPdf()`.

- [ ] **Step 4: Run test to verify Task 3 passes**

Run: `DB_DATABASE=qrsar_test php tests/test_bra_pdf_export.php`  
Expected: PASS untuk seluruh asersi frontend markup dan JavaScript integration.

- [ ] **Step 5: Run JS test suites**

Run: `node --test tests/js/*.test.js`  
Expected: 6 tests pass, 0 fail.

- [ ] **Step 6: Commit Task 3**

```bash
git add assets/js/pdf_document_exporter.js views/bra/view.php tests/test_bra_pdf_export.php
git commit -m "feat(bra): integrate official vector PDF download button in BRA view"
```

---

### Task 4: Daftarkan Test ke Unified Test Runner & Verifikasi Menyeluruh

**Files:**
- Modify: `tests/run_all.php`

- [ ] **Step 1: Register `test_bra_pdf_export.php` into `tests/run_all.php`**

Tambahkan `'test_bra_pdf_export.php'` ke daftar suite pengujian di `tests/run_all.php`.

- [ ] **Step 2: Run all PHP tests with test database**

Run: `DB_DATABASE=qrsar_test php tests/run_all.php`  
Expected: 88/88 test suites PASS (0 FAIL).

- [ ] **Step 3: Run all JavaScript tests**

Run: `node --test tests/js/*.test.js`  
Expected: 6/6 test suites PASS (0 FAIL).

- [ ] **Step 4: Commit Task 4**

```bash
git add tests/run_all.php
git commit -m "test: register test_bra_pdf_export into unified test runner (88/88 PASS)"
```

