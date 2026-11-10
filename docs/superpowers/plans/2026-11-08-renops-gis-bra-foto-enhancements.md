# RENOPS, LAPBANG, GIS, BRA SAR & Foto Dokumentasi Enhancements Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki 7 poin operasional: meniadakan pola pencarian default otomatis di RENOPS, mengaktifkan drag-and-drop unsur/alut di RENOPS & LAPBANG, mendukung unggah 12 foto sekaligus (total ~60 MB), memindahkan foto dokumentasi ke halaman lampiran terakhir pasca-SMC, menetapkan label LKP default "LKP" tanpa simbol, menambahkan filter dropdown kategori titik dan merampingkan card di GIS & import RENOPS, serta memisahkan baris Entry dan Exit koordinat pada BRA SAR.

**Architecture:** 
- UI form operasional diperkaya dengan SortableJS lokal yang konsisten (`operasi_form`, `renops_form`, `sitrep_form`).
- Layout dokumen SMEAC memindahkan foto ke lembar portrait terpisah di bagian akhir sebagai Lampiran Dokumentasi dengan aturan anti-pecah (`break-inside: avoid`).
- Penanganan unggah foto mendukung batch request terpusat dan runtime `php.ini` diselaraskan untuk muatan hingga 60-128 MB.
- Generator BRA SAR memecah klausa entry/exit menjadi dua baris independen.
- GIS data layer menstandardisasi label awal LKP menjadi string bersih `'LKP'`.

**Tech Stack:** PHP 8.5, MySQL, vanilla JavaScript, SortableJS 1.15.6, HTML5 / CSS media print, Node test runner.

**Spec:** `/Users/irfandydk/antigravity/QR-SAR/docs/superpowers/specs/2026-11-08-renops-gis-bra-foto-enhancements-design.md`

## Global Constraints

- Jangan melakukan git commit otomatis (git commit dilakukan oleh pengguna).
- Bekerja langsung pada branch aktif (`main`).
- Bahasa teks antarmuka dan laporan: Bahasa Indonesia baku.
- Menjaga integritas 81 automated test suite (`php tests/run_all.php`).

## Review Focus

1. Form input RENOPS yang dimuat tanpa data pola dari GIS tidak boleh secara gaib memunculkan "Parallel Track Search" di form maupun saat disimpan.
2. Penataan ulang urutan (drag-and-drop) unsur dan alut pada RENOPS dan LAPBANG harus sinkron ke hidden JSON dan textarea teks.
3. Unggah 12 foto berukuran masing-masing 4–5 MB (total ~60 MB) berhasil tersimpan tanpa terhalang limit upload web server atau timeout GD.
4. Lampiran foto pada dokumen RENOPS tercetak rapi di lembar tersendiri setelah blok tanda tangan SMC tanpa memecah card foto di tengah halaman.
5. BRA SAR yang di-generate dari data RENOPS ber-pola pencarian menampilkan koordinat entry dan exit pada baris terpisah.

---

### Task 1: Hapus default pola pencarian 'Parallel Track Search' pada form input RENOPS

**Files:**
- Modify: `assets/js/pages/renops_form.js:307, 392, 467, 1334, 1336, 1341, 1363, 1452, 1570`

**Interfaces:**
- Input: State SRU baru atau SRU yang diimpor tanpa pola pencarian.
- Output: `pola_pencarian` bernilai `''` (kosong), UI menampilkan `"- (Belum ditentukan / Pilih dari Peta Plotting)"`.

- [ ] **Step 1: Modifikasi nilai default `pola_pencarian` di `assets/js/pages/renops_form.js`**
  - Ganti default `'Parallel Track Search'` dengan `''` pada objek `SRU_DEFAULTS`, penambahan SRU manual (`addSruRow`), dan fallback `s.pola_pencarian`.
  - Pada render badge / teks pola SRU: jika `pola_pencarian` kosong, tampilkan teks abu-abu `"- (Belum ditentukan / Pilih dari Peta Plotting)"`.
- [ ] **Step 2: Verifikasi perilaku di browser / skrip pengujian**
  - Pastikan penambahan SRU baru menghasilkan `pola_pencarian: ''`.
  - Pastikan impor Search Area poligon biasa tanpa search pattern menghasilkan `pola_pencarian: ''`.

---

### Task 2: Terapkan drag-and-drop (SortableJS) pada Unsur dan Alut di RENOPS dan LAPBANG

**Files:**
- Modify:
  - `views/operasi/renops_form.php`
  - `assets/js/pages/renops_form.js`
  - `views/operasi/sitrep_form.php`
  - `assets/js/pages/sitrep_form.js`

**Interfaces:**
- Consumes: `assets/vendor/sortablejs/Sortable.min.js`.
- Produces: Drag-to-reorder pada kontainer `#unsur-items-container` dan `#alut-items-container` di form RENOPS dan LAPBANG.

- [ ] **Step 1: Muat script `Sortable.min.js` di `views/operasi/renops_form.php` dan `views/operasi/sitrep_form.php`**
  - Tambahkan tag `<script src="<?= assetUrl('assets/vendor/sortablejs/Sortable.min.js') ?>"></script>` sebelum file JS masing-masing.
- [ ] **Step 2: Tambahkan inisialisasi Sortable dan sinkronisasi urutan di `assets/js/pages/renops_form.js`**
  - Tambahkan variabel `unsurSortable` dan `alutSortables`.
  - Pada `renderUnsurList()`, inisialisasi `new Sortable(container, { handle: '.drag-handle', animation: 150, onEnd: ... })` untuk reorder `currentUnsurList` dan update JSON/textarea.
  - Pada `renderAlutList()`, terapkan Sortable serupa pada kartu-kartu alut.
  - Tambahkan atribut drag handle dan kursor `cursor-grab active:cursor-grabbing` pada card item.
- [ ] **Step 3: Tambahkan inisialisasi Sortable dan sinkronisasi urutan di `assets/js/pages/sitrep_form.js`**
  - Terapkan logika yang sama untuk `currentUnsurList` dan `currentAlutList` di LAPBANG.
- [ ] **Step 4: Jalankan verifikasi sintaks dan pengujian form RENOPS / LAPBANG**
  - Jalankan `DB_DATABASE=qrsar_test php tests/test_renops_lapbang_phase1.php`.

---

### Task 3: Konfigurasi php.ini & optimasi unggah foto sekaligus (hingga 60 MB)

**Files:**
- Modify:
  - `/opt/homebrew/etc/php/8.5/php.ini` (lokal developer server)
  - `assets/js/pages/renops_dokumen.js`
  - `api/controllers/RenopsDokumenApiController.php`

**Interfaces:**
- Consumes: Array 12 berkas gambar ($\le$ 5 MB masing-masing, total $\le$ 60 MB).
- Produces: Unggah sekaligus berhasil tersimpan ke folder `uploads/renops_foto` dan database `RenopsFoto`.

- [ ] **Step 1: Perbarui `/opt/homebrew/etc/php/8.5/php.ini`**
  - Ubah `upload_max_filesize = 20M` (dari 2M).
  - Ubah `post_max_size = 128M` (dari 8M).
  - Ubah `memory_limit = 512M` (dari 128M).
- [ ] **Step 2: Sesuaikan pengirim batch di `assets/js/pages/renops_dokumen.js`**
  - Tambahkan mode pengiriman batch langsung di mana seluruh 12 file dimasukkan ke dalam satu `FormData` (`foto[]` dan `keterangan[]`).
  - Tampilkan status progress yang informatif selama proses berlangsung.
- [ ] **Step 3: Verifikasi backend di `RenopsDokumenApiController.php`**
  - Pastikan alokasi memory dan batas eksekusi aman (`set_time_limit(300)`).
- [ ] **Step 4: Jalankan pengujian foto upload**
  - Jalankan `DB_DATABASE=qrsar_test php tests/test_renops_foto_upload.php`.
  - Jalankan `DB_DATABASE=qrsar_test php tests/test_renops_dokumen_editor.php`.

---

### Task 4: Pindahkan foto dokumentasi ke halaman terakhir sebagai Lampiran setelah pengesahan SMC

**Files:**
- Modify:
  - `views/operasi/partials/renops_smeac_doc.php`
  - `tests/test_renops_dokumen_editor.php`

**Interfaces:**
- Output: Halaman mandiri `article.page-sheet.renops-smeac.hal-lampiran-foto` di akhir dokumen RENOPS setelah penandatanganan SMC.

- [ ] **Step 1: Pindahkan blok foto dari Bagian D ke Lampiran Akhir di `renops_smeac_doc.php`**
  - Pindahkan blok `foto-grid` dan tombol `#btnUploadFoto` ke setelah penutup `</article>` lembar landscape pengesahan SMC.
  - Buat lembar baru: `<article class="page-sheet renops-smeac hal-lampiran-foto<?= $draft ? ' is-draft' : '' ?>" data-pdf-orientation="portrait">`.
  - Tambahkan judul lampiran: `<h2 class="h1">LAMPIRAN: DOKUMENTASI OPERASI SAR</h2>`.
- [ ] **Step 2: Tambahkan aturan CSS cetak anti-pecah**
  - Tambahkan `.hal-lampiran-foto { break-before: page; page-break-before: always; }`.
  - Pastikan `.renops-smeac figure.foto { break-inside: avoid; page-break-inside: avoid; }`.
- [ ] **Step 3: Perbarui penegasan di `tests/test_renops_dokumen_editor.php`**
  - Sesuaikan pengujian lokasi tombol upload foto agar memvalidasi posisinya di lembar lampiran dokumentasi.
- [ ] **Step 4: Jalankan pengujian editor dokumen RENOPS**
  - Jalankan `DB_DATABASE=qrsar_test php tests/test_renops_dokumen_editor.php`.

---

### Task 5: Normalisasi nama default titik LKP menjadi "LKP" tanpa simbol

**Files:**
- Modify:
  - `models/GisModel.php:791, 796`
  - `assets/js/pages/operasi_form.js:554`
  - `assets/js/pages/operasi_form_map.js:152`

**Interfaces:**
- Produces: Titik LKP default memiliki label `'LKP'` dan pin badge `'LKP'`.

- [ ] **Step 1: Ubah label LKP di `models/GisModel.php`**
  - Ganti `"🚨 LKP - " . $namaOperasi` dengan `'LKP'`.
  - Ganti `alphabetChar: '🚨'` dengan `'LKP'` (atau `'L'`).
- [ ] **Step 2: Ubah pin badge LKP di `operasi_form.js` dan `operasi_form_map.js`**
  - Ganti badge teks `🚨 LKP` dengan `LKP`.
- [ ] **Step 3: Jalankan pengujian GIS LKP**
  - Jalankan `DB_DATABASE=qrsar_test php tests/test_lkp_routing.php`.

---

### Task 6: Dropdown kategori titik & card minimalis di Peta GIS dan Import Plotting RENOPS

**Files:**
- Modify:
  - `views/gis.php`
  - `assets/js/gis_master.js`
  - `views/operasi/renops_form.php`
  - `assets/js/pages/renops_form.js`

**Interfaces:**
- Produces:
  - Dropdown filter kategori titik di tab titik GIS dan modal import RENOPS.
  - Kartu titik yang ringkas, rapi, dan mudah dibaca (compact styling).

- [ ] **Step 1: Tambahkan dropdown kategori di `views/gis.php` dan `assets/js/gis_master.js`**
  - Di atas `savedPointsListContainer`, tambahkan `<select id="filterGisPointCategory" ...>`.
  - Pada `renderSavedPointsList()`, filter titik berdasarkan kategori yang dipilih.
  - Rapikan kartu titik dengan padding dan tombol yang lebih presisi.
- [ ] **Step 2: Tambahkan dropdown kategori dan perampingan card di `renops_form.php` dan `renops_form.js`**
  - Pada tab `points` di modal import RENOPS, sediakan `<select id="filter-renops-point-cat" ...>`.
  - Rampingkan kartu titik menjadi `p-2.5 rounded-xl border` dengan tombol "Sisipkan ke SRU" inline.
- [ ] **Step 3: Uji fungsionalitas filter kategori dan tampilan card**
  - Pastikan filter kategori menyaring daftar secara instan tanpa reload.

---

### Task 7: Pisahkan Entry dan Exit Koordinat pada BRA SAR menjadi dua baris terpisah

**Files:**
- Modify:
  - `assets/js/bra/bra_templates.js:215`
  - `tests/js/bra_templates.test.js:116`

**Interfaces:**
- Consumes: Objek SRU dengan `koordinat_area` memuat Entry dan/atau Exit Poin.
- Produces: Dua baris terpisah:
  `ENTRY KOORDINAT : [koordinat]`
  `EXIT KOORDINAT : [koordinat]`

- [x] **Step 1: Tulis/perbarui pengujian gagal di `tests/js/bra_templates.test.js`**
  - Ubah ekspektasi regex dari `ENTRY DAN EXIT POINT` menjadi memeriksa keberadaan `ENTRY KOORDINAT :` dan `EXIT KOORDINAT :`.
- [x] **Step 2: Implementasikan perubahan di `assets/js/bra/bra_templates.js`**
  - Ganti:
    `if (area.entry || area.exit) lines.push('ENTRY DAN EXIT POINT ' + upper(area.entry) + ' ' + upper(area.exit)).trim());`
    Menjadi:
    ```javascript
    if (area.entry) lines.push(`ENTRY KOORDINAT : ${upper(area.entry)}`);
    if (area.exit) lines.push(`EXIT KOORDINAT : ${upper(area.exit)}`);
    ```
- [x] **Step 3: Jalankan uji JS BRA SAR**
  - Jalankan `node tests/js/bra_templates.test.js`.
- [x] **Step 4: Jalankan pengujian PHP BRA SAR**
  - Jalankan `DB_DATABASE=qrsar_test php tests/test_bra_perfection.php`.

---

### Task 8: Full Regression Suite Verification

- [ ] **Step 1: Jalankan seluruh rangkaian 81 test suite otomatis**
  - Jalankan `DB_DATABASE=qrsar_test php tests/run_all.php`.
- [ ] **Step 2: Pastikan 81/81 test suites lolos 100% tanpa ada kegagalan**

