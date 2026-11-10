# Spesifikasi Rancangan: Perbaikan RENOPS, LAPBANG, GIS, BRA SAR & Foto Dokumentasi

**Tanggal:** 8 November 2026  
**Status:** Disetujui  
**Target Komponen:** Form RENOPS, Form LAPBANG, Peta GIS, BRA SAR, Dokumen SMEAC RENOPS, Konfigurasi Server PHP.

---

## 1. Latar Belakang & Tujuan
Berdasarkan evaluasi alur operasional dan pengujian pengguna, ditemukan 7 area yang memerlukan penyempurnaan agar aplikasi QR-SAR lebih akurat, responsif, dan rapi secara estetika maupun tata letak dokumen:

1. Pola pencarian pada form input RENOPS tidak boleh menetapkan default "Parallel Track Search" jika belum ada input/pilihan pola dari operator.
2. Drag-and-drop reordering pada unsur SAR dan alut/palsar perlu diterapkan di form input RENOPS dan form input LAPBANG (konsisten dengan Laporan Awal).
3. Unggah foto dokumentasi pada draf dokumen RENOPS harus dapat memproses hingga 12 foto sekaligus (maksimal 5 MB per foto, total muatan payload ~60 MB) tanpa kegagalan timeout atau limit server.
4. Foto dokumentasi pada dokumen RENOPS dipindahkan ke lembar lampiran terakhir (setelah penandatanganan SMC), dengan tata letak rapi (`break-inside: avoid`) agar tidak terpotong antar halaman cetak/PDF.
5. Nama titik LKP default saat laporan awal dibuat harus bersih "LKP" tanpa simbol sirine (🚨).
6. Filter dropdown kategori titik pada Peta GIS (detail operasi) dan pada modal Import Plotting RENOPS (Titik Taktis & LKP), disertai penyederhanaan dan perampingan card titik agar ringkas dan enak dilihat.
7. Format naskah BRA SAR untuk koordinat Entry dan Exit Point yang diimpor dari RENOPS harus dipisahkan menjadi dua baris mandiri: `ENTRY KOORDINAT : xxx` dan `EXIT KOORDINAT : xxx` (bukan digabung dalam 1 kalimat).

---

## 2. Rincian Teknis & Arsitektur

### 2.1 Pola Pencarian RENOPS (`assets/js/pages/renops_form.js`)
- Mengosongkan default `pola_pencarian` dari `'Parallel Track Search'` menjadi string kosong `''`.
- Pada UI form SRU, jika `pola_pencarian` kosong atau falsy, tampilkan teks placeholder `"- (Belum ditentukan / Pilih dari Peta Plotting)"` dan jangan mengisi hidden input/field dengan nilai bawaan otomatis.
- Nilai baru terisi hanya apabila operator mengimpor pola dari GIS atau memilih pola secara eksplisit.

### 2.2 Drag-and-Drop Unsur & Alut (`renops_form` & `sitrep_form`)
- Memuat pustaka `Sortable.min.js` lokal (`assets/vendor/sortablejs/Sortable.min.js`) via `assetUrl()` pada `views/operasi/renops_form.php` dan `views/operasi/sitrep_form.php`.
- Menambahkan kursor visual `cursor-grab active:cursor-grabbing` pada pegangan kartu unsur dan alut.
- Mengintegrasikan inisialisasi `Sortable` pada `#unsur-items-container` dan `#alut-items-container` di `renops_form.js` dan `sitrep_form.js` dengan sinkronisasi DOM ke `currentUnsurList` / `currentAlutList` serta pembaruan hidden JSON dan textarea teks.

### 2.3 Unggah 12 Foto Sekaligus (~60 MB)
- Mengonfigurasi `/opt/homebrew/etc/php/8.5/php.ini` (runtime PHP server lokal):
  - `upload_max_filesize = 20M`
  - `post_max_size = 128M`
  - `memory_limit = 512M`
- Di `assets/js/pages/renops_dokumen.js`:
  - Mendukung pengiriman batch langsung 12 foto dalam 1 request `FormData` (`foto[]` dan `keterangan[]`) hingga 60 MB total payload.
  - Memberikan indikator progres/status yang informatif saat berkas diproses di server.
- Di `RenopsDokumenApiController.php` & `RenopsFotoModel.php`:
  - Mengamankan eksekusi waktu dengan `set_time_limit(300)` dan pelepasan memori GD `imagedestroy()` pada setiap iterasi foto.

### 2.4 Foto Dokumentasi sebagai Lampiran Halaman Terakhir (`views/operasi/partials/renops_smeac_doc.php`)
- Memindahkan blok foto dokumentasi dan tombol unggah dari bagian D (Tahap Operasi) ke lembar portrait mandiri paling belakang:
  ```html
  <article class="page-sheet renops-smeac hal-lampiran-foto<?= $draft ? ' is-draft' : '' ?>" data-pdf-orientation="portrait">
      <h2 class="h1">LAMPIRAN: DOKUMENTASI OPERASI SAR</h2>
      ...
  </article>
  ```
- Menambahkan aturan CSS cetak:
  - `.hal-lampiran-foto { break-before: page; page-break-before: always; }`
  - `.renops-smeac figure.foto { break-inside: avoid; page-break-inside: avoid; }`
- Mengatur penomoran gambar agar urut dan tombol aksi terpusat di bawah grid foto.

### 2.5 Nama Titik LKP Default (`models/GisModel.php`, `operasi_form.js`, `operasi_form_map.js`)
- Di `GisModel::syncOperasiLkpPoint()`:
  - Mengubah `'label' => 'LKP'` (menggantikan `'🚨 LKP - ' . $namaOps`).
  - Mengubah `'alphabetChar' => 'LKP'`.
- Di `operasi_form.js` dan `operasi_form_map.js`:
  - Mengubah label badge pin LKP menjadi `LKP` polos tanpa emoji sirine.

### 2.6 Dropdown Kategori Titik & Minimalis Card
- **Peta GIS (`views/gis.php`, `assets/js/gis_master.js`):**
  - Menambahkan `<select id="filterGisPointCategory">` di atas daftar titik tersimpan.
  - Opsi dropdown otomatis dihimpun dari kategori titik yang ada (`LKP`, `Pangkalan/Dermaga`, `Potensi SAR`, dll) ditambah opsi default "Semua Kategori".
  - Merampingkan card titik: padding lebih kecil (`py-1 px-2`), font koordinat monospaced rapi, tombol aksi teratur.
- **Import Plotting RENOPS (`views/operasi/renops_form.php`, `assets/js/pages/renops_form.js`):**
  - Menambahkan `<select id="filter-renops-point-cat">` pada tab "Titik Taktis & LKP".
  - Mendesain ulang card titik taktis: lebih kecil dan elegan (`p-2.5 rounded-xl border`), meminimalkan whitespace berlebih, tombol "Sisipkan ke SRU" dibuat ringkas.

### 2.7 BRA SAR Pemisahan Entry & Exit (`assets/js/bra/bra_templates.js`)
- Mengubah fungsi `sruBlock()`:
  - Sebelum:
    `if (area.entry || area.exit) lines.push('ENTRY DAN EXIT POINT ' + upper(area.entry) + ' ' + upper(area.exit)).trim());`
  - Sesudah:
    ```javascript
    if (area.entry) lines.push(`ENTRY KOORDINAT : ${upper(area.entry)}`);
    if (area.exit) lines.push(`EXIT KOORDINAT : ${upper(area.exit)}`);
    ```
- Memperbarui tes `tests/js/bra_templates.test.js` dan verifikasi naskah BRA SAR.

---

## 3. Rencana Pengujian
- Unit test JS: `node tests/js/bra_templates.test.js`
- Regression test PHP:
  - `DB_DATABASE=qrsar_test php tests/test_renops_dokumen_editor.php`
  - `DB_DATABASE=qrsar_test php tests/test_renops_foto_upload.php`
  - `DB_DATABASE=qrsar_test php tests/test_lkp_routing.php`
  - `DB_DATABASE=qrsar_test php tests/test_bra_perfection.php`
  - `DB_DATABASE=qrsar_test php tests/run_all.php` (81 suite lulus 100%)

