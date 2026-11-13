# Design Spec: BRA SAR Vector PDF Export & Print Consistency

**Tanggal:** 13 November 2026  
**Status:** Disetujui Pengguna (Pendekatan A)  
**Terkait:** Lembar Dokumen Berita Radio SAR (BRA), Cetak & Unduh PDF Vektor  

---

## 1. Intent and The Why

Pengguna menginginkan fitur **Unduh PDF** dan **Cetak PDF** pada lembar dokumen Berita Radio SAR (BRA) memiliki kualitas prima dan konsistensi yang sama persis seperti pada RENOPS:
- Berkas PDF hasil unduhan harus berupa dokumen PDF vektor murni di mana teksnya tajam, dapat dicari (*searchable / findable* via `Ctrl+F`), dan dapat disalin (*selectable*), bukan dalam bentuk raster tangkapan layar gambar (*canvas screenshot*).
- Hasil unduhan PDF langsung dari tombol *"Unduh PDF"* dan hasil cetak browser dari dialog *"Cetak / Simpan PDF"* (`window.print()`) harus 100% konsisten, proporsional, dan identik.

Saat ini tombol di `views/bra/view.php` berlabel *"PDF Gambar"* yang memanggil pustaka `html2pdf.js` (html2canvas) yang merasterisasi elemen menjadi gambar JPEG di dalam berkas PDF.

---

## 2. Goals, Non-Goals, and Anti-Goals

### Goals
1. Mengganti tombol *"PDF Gambar"* di `views/bra/view.php` menjadi tombol resmi **"Unduh PDF"** yang mengalirkan PDF vektor dari server.
2. Memastikan teks dalam PDF hasil unduhan 100% *searchable/selectable* dan visualnya identik dengan keluaran `@media print` browser (`window.print()`).
3. Menyediakan service backend [`BraPdfService`](file:///Users/irfandydk/antigravity/QR-SAR/services/BraPdfService.php) yang merender HTML dokumen BRA menjadi biner `%PDF-` menggunakan Google Chrome / Chromium Headless `--print-to-pdf`.
4. Menyediakan handler `?format=pdf` pada [`BraController::showDocument()`](file:///Users/irfandydk/antigravity/QR-SAR/controllers/BraController.php) baik untuk petugas internal yang terotentikasi maupun verifikasi publik bertanda tangan digital/token valid.
5. Memperbarui [`assets/js/pdf_document_exporter.js`](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/pdf_document_exporter.js) agar mengenali `#official-bra-document` sebagai target dokumen resmi.
6. Memastikan seluruh 87 PHP test suite dan 6 JS test suite tetap 100% lulus (PASS).

### Non-Goals
- Mengubah struktur konten/redaksional telegram BRA yang sudah ada.
- Mengubah format layout cetak lembar telegram Basarnas (border 2px, label SENKOM kiri, tabel grid tetap dipertahankan).
- Mengubah perilaku verifikasi kriptografis token BRA.

### Anti-Goals
- Tidak merasterisasi teks atau tabel menjadi format gambar (JPG/PNG).
- Tidak memutus alur cetak fisik browser (`window.print()`).
- Tidak mengubah pustaka RENOPS yang sudah berjalan stabil.

---

## 3. Constraints

- **Platform Target:** Server PHP native (Apache/CLI) dengan biner Google Chrome / Chromium Headless terpasang (`/Applications/Google Chrome.app/...` pada macOS, atau `/usr/bin/google-chrome` pada Linux).
- **Graceful Fallback:** Jika biner Chrome Headless tidak tersedia di server, tombol unduh harus fallback secara cerdas ke `window.print()` browser (seperti pola `pdf_document_exporter.js`).
- **Security & Authorization:** Unduhan `?format=pdf` harus tunduk pada aturan otorisasi yang sama dengan tampilan dokumen: petugas terautentikasi atau publik yang memegang token valid. Permintaan token yang tidak sah harus mengembalikan 404/invalid verify.
- **Nama Berkas Standar:** Nama file unduhan harus otomatis terformat bersih: `BRA-SAR-{NO_BRA_SANITIZED}.pdf`.

---

## 4. Approaches Considered

1. **Pendekatan A (Dipilih): Dedicated `BraPdfService` + Endpoint `?format=pdf` + Integrasi `pdf_document_exporter.js`**
   - Menggunakan engine Chrome Headless `--print-to-pdf` yang terbukti handal pada RENOPS.
   - Terisolasi penuh pada service `BraPdfService` tanpa menyentuh modul RENOPS yang sudah stabil.
   - Memanfaatkan CSS `@media print` yang sudah disetel rapi di `assets/css/style.css` untuk `#official-bra-document`.
   
2. **Pendekatan B (Ditolak): Refactor Bersama ke `PdfRenderService` Universal**
   - Menggabungkan `RenopsPdfService` dan `BraPdfService` menjadi satu kelas global.
   - Ditolak karena menimbulkan risiko regresi pada RENOPS dan memerlukan modifikasi ulang pada test suite RENOPS yang sudah tervalidasi 100%.

3. **Pendekatan C (Ditolak): Client-Side jsPDF Vektor Rendering**
   - Merender vektor langsung di JavaScript browser.
   - Ditolak karena rendering border tebal, font mono, teks vertikal SENKOM, dan margin multi-halaman pada jsPDF client-side sangat rentan bergeser dan tidak konsisten dengan `window.print()`.

---

## 5. Architectural & Technical Decisions

### 5.1 Backend: `BraPdfService` ([`services/BraPdfService.php`](file:///Users/irfandydk/antigravity/QR-SAR/services/BraPdfService.php))
- `findChromeBinary(): ?string` mencari path eksekusi Chrome/Chromium di server.
- `prepareHtmlForLocalRender(string $html): string`:
  - Mengubah tautan aset `/assets/...` dan `/uploads/...` menjadi URI absolut `file://...` lokal.
  - Menginjeksi CSS print override untuk menyembunyikan elemen `.print:hidden`, `header`, `footer`, navbar, background gelap, dan memastikan `#official-bra-document` tampil tanpa bayangan luar dengan lebar cetak A4 180mm.
- `renderHtmlToPdf(string $html): ?string`:
  - Menyimpan HTML ke file sementara di sys temp dir.
  - Menjalankan Google Chrome Headless `--print-to-pdf` dengan argumen: `--headless --disable-gpu --no-sandbox --disable-dev-shm-usage --no-pdf-header-footer --virtual-time-budget=1200`.
  - Membaca biner `%PDF-`, memvalidasi ukuran > 500 byte, dan membersihkan file sementara.

### 5.2 Controller: `BraController::showDocument()` ([`controllers/BraController.php`](file:///Users/irfandydk/antigravity/QR-SAR/controllers/BraController.php))
- Memeriksa jika `($_GET['format'] ?? '') === 'pdf'`.
- Menyiapkan variabel view dan merender `bra/view` ke buffer output (`ob_start() ... ob_get_clean()`).
- Memanggil `BraPdfService::renderHtmlToPdf($html)`.
- Mengalirkan header HTTP:
  - `Content-Type: application/pdf`
  - `Content-Disposition: attachment; filename="BRA-SAR-...pdf"`
  - `Content-Length: ...`
  - `Cache-Control: no-store, no-cache, must-revalidate`
- Jika gagal/Chrome tidak ada, kembalikan status HTTP 503 JSON agar frontend beralih ke fallback.

### 5.3 Frontend: `views/bra/view.php` & `assets/js/pdf_document_exporter.js`
- Di [`views/bra/view.php`](file:///Users/irfandydk/antigravity/QR-SAR/views/bra/view.php):
  - Memuat `<script src="<?= assetUrl('assets/js/pdf_document_exporter.js') ?>"></script>`.
  - Menambahkan class `.page-sheet` dan/atau memperbarui selektor `pdf_document_exporter.js` agar mendukung `#official-bra-document`.
  - Mengganti tombol `"PDF Gambar"` dengan:
    ```html
    <button type="button" id="btnDownloadPdf" 
            data-pdf-filename="<?= e($pdfFilename) ?>" 
            data-pdf-url="<?= e($pdfDownloadUrl) ?>" 
            onclick="downloadOfficialPdf(null, this)" 
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-md transition" 
            title="Unduh berkas PDF resmi (teks tajam &amp; dapat dicari / searchable, identik dengan hasil cetak)">
      <svg class="w-3.5 h-3.5" ...></svg>
      <span>Unduh PDF</span>
    </button>
    ```
  - Menghapus method legacy `window.downloadBraDocumentPdf()`.

### 5.4 Test Suite Otomatis ([`tests/test_bra_pdf_export.php`](file:///Users/irfandydk/antigravity/QR-SAR/tests/test_bra_pdf_export.php))
- Menguji ketersediaan `BraPdfService`.
- Menguji `prepareHtmlForLocalRender` mengubah asset path menjadi URI `file://`.
- Menguji rendering biner PDF `%PDF-` via Chrome Headless.
- Menguji endpoint `bra.php?view=ID&format=pdf` (internal) dan `bra.php?view=ID&token=TOKEN&format=pdf` (publik).
- Menguji penolakan dokumen tidak sah / token salah pada parameter `?format=pdf`.

