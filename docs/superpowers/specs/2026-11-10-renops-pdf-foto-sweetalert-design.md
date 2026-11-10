# Spesifikasi Desain: Optimasi PDF Vektor RENOPS, Drag-Reorder & Bulk Delete Foto Dokumentasi, serta Standardisasi SweetAlert2

**Tanggal:** 10 November 2026  
**Proyek:** QR-SAR Basarnas Kendari  
**Status:** Menunggu Persetujuan Pengguna  

---

## 1. Tujuan & Latar Belakang (Intent & Why)

Dokumen Rencana Operasi SAR (RENOPS) berformat SMEAC merupakan dokumen operasi resmi yang harus memenuhi standar ketajaman cetak, kemudahan penyuntingan draf, serta konsistensi antarmuka pengguna (UX):

1. **Keseragaman Hasil Unduh PDF & Cetak PDF (Teks Dapat Dicari / *Find*):**
   Sebelumnya, tombol **Unduh PDF / PDF Gambar** menggunakan `html2pdf.js` (`html2canvas`) yang memotret halaman menjadi gambar raster JPEG dan menimpa tipografi ke *Times New Roman* 12pt. Akibatnya, hasil unduhan berbeda jauh dari tampilan **Cetak PDF** (`window.print()`) dan teks di dalam PDF tidak dapat dicari (*Ctrl+F* / *Find*). Pengguna menginginkan hasil **Unduh PDF** memiliki tampilan yang 100% identik dengan **Cetak PDF** dan teksnya dapat dicari (*searchable vector PDF*).
2. **Reorder Foto Dokumentasi dengan *Drag-and-Drop* (Tanpa Tombol Manual):**
   Tombol manual naik/turun (`▲` / `▼`) pada tiap kartu foto dokumentasi di lampiran draf RENOPS kurang praktis ketika mengatur hingga 12 foto lintas halaman. Pengguna menginginkan pengurutan ulang cukup dengan menarik (*drag-and-drop*) foto secara langsung tanpa tombol reorder manual.
3. **Penghapusan Massal (*Bulk Delete*) Foto Dokumentasi:**
   Menghapus banyak foto satu per satu memakan waktu. Pengguna membutuhkan fitur pilih banyak foto (*checkbox* per foto + *Pilih Semua*) dan tombol **Hapus Terpilih** untuk menghapus beberapa atau seluruh foto dokumentasi sekaligus.
4. **Standardisasi Dialog Konfirmasi & Peringatan dengan SweetAlert2:**
   Di halaman editor draf RENOPS dan beberapa skrip terkait masih terdapat panggilan `window.confirm()` dan `window.alert()` yang memunculkan dialog bawaan browser (`localhost says...`). Seluruh dialog konfirmasi dan peringatan harus menggunakan komponen modal **SweetAlert2** (`Swal.fire` / `confirmAction` / `showErrorAlert`) agar seragam, modern, dan mendukung tema terang/gelap aplikasi.

---

## 2. Goals, Non-Goals, & Anti-Goals

### Goals (Sasaran Utama)
- **PDF Vektor Identik Cetak (Opsi A):** Menyediakan generator PDF sisi server menggunakan engine cetak Google Chrome Headless (`--headless --print-to-pdf`) untuk dokumen RENOPS (baik mode `TERBIT`, `DRAF`/pratinjau, maupun `renops_publik`), dengan *fallback* otomatis ke `window.print()` bila biner Chrome tidak ditemukan di lingkungan server.
- **Drag-and-Drop Foto Lintas Lembar Lampiran:** Mengintegrasikan `SortableJS` pada `.foto-grid` di halaman editor draf RENOPS (`views/operasi/renops_dokumen.php` & `assets/js/pages/renops_dokumen.js`), menghapus tombol manual `▲`/`▼`, merapikan distribusi kartu maksimal 6 foto per halaman A4 secara instan setelah *drop*, memperbarui penomoran `Gambar X.` secara *real-time*, dan menyimpan urutan ke server melalui transaksi *batch*.
- **Bulk Delete Foto Dokumentasi:** Menambahkan *checkbox* seleksi pada setiap kartu foto dokumentasi di mode draf, bilah kontrol **Pilih Semua** + **Hapus Terpilih (N)**, serta endpoint *batch delete* yang memvalidasi kepemilikan foto terhadap `id_renops` dan menghapus berkas fisik secara aman.
- **Zero Native Dialog (`window.confirm` / `window.alert`):** Mengganti seluruh dialog native browser di `assets/js/pages/renops_dokumen.js`, `assets/js/pdf_document_exporter.js`, dan `assets/js/pages/operasi_detail.js` dengan modal **SweetAlert2**.

### Non-Goals (Di Luar Cakupan)
- Tidak mengubah format dokumen BRA SAR atau laporan lain di luar dokumen RENOPS.
- Tidak mengubah batas kuota maksimal 12 foto per RENOPS dan maksimal 5 MB per foto yang sudah berjalan stabil.

### Anti-Goals (Hal yang Harus Dihindari)
- Jangan sampai unduhan PDF RENOPS menghasilkan gambar raster yang teksnya tidak bisa dicari (*non-searchable*) selama engine Chrome tersedia.
- Jangan sampai *drag-and-drop* foto mengganggu penyuntingan teks keterangan foto (`.foto-ket[contenteditable]`) atau klik tombol hapus/*checkbox*.
- Jangan sampai satu halaman lampiran foto menampung lebih dari 6 foto setelah foto digeser antar-halaman lampiran.
- Jangan meninggalkan *file* sementara (`.html` / `.pdf`) di direktori `sys_get_temp_dir()` setelah proses unduh PDF selesai.

---

## 3. Batasan Teknis & Keamanan (Constraints)

1. **Keamanan Eksekusi Subproses PDF (`RenopsPdfService`):**
   - Seluruh argumen CLI ke biner Chrome wajib di-*escape* dengan `escapeshellarg()`.
   - Berkas sementara HTML dan PDF dibuat menggunakan nama acak kriptografis (`bin2hex(random_bytes(8))`) di `sys_get_temp_dir()` dan wajib dihapus di blok `finally`.
   - Aset lokal (`/assets/...` dan `/uploads/...`) pada HTML sementara dikonversi menjadi path lokal `file://` yang tervalidasi berada di dalam `ROOT_DIR` (mencegah SSRF atau akses berkas di luar direktori aplikasi), serta diberi flag `--headless --disable-gpu --no-pdf-header-footer --virtual-time-budget=1500` agar QR Code `<canvas>` sempat dirender ke `<img>` sebelum dicetak ke PDF.
2. **Keamanan API Reorder & Bulk Delete Foto (`RenopsDokumenApiController` & `RenopsFotoModel`):**
   - Wajib melalui `self::requireAuth()` dan validasi token CSRF (`X-CSRF-Token` pada header `fetch`).
   - Seluruh `id_foto` yang dikirim pada *reorder* maupun *bulk delete* wajib diverifikasi milik `id_renops` terkait di dalam transaksi database (`WHERE id_renops = :r AND id_foto = :i`). ID foto milik RENOPS lain harus ditolak atau diabaikan secara aman tanpa kebocoran data.
   - Penghapusan berkas fisik (`RenopsDokumenModel::deleteFiles()`) hanya dilakukan setelah transaksi `DELETE` di database berhasil (`commit`). Versi `TERBIT` tetap aman karena menggunakan salinan berkas beku (*frozen copy*).
3. **Kompatibilitas Pengujian Regresi:**
   - Mempertahankan tingkat kelulusan 100% pada seluruh 81 *test suite* PHP (`tests/run_all.php`) dan 6 *test suite* JS.
   - Tidak melakukan *git commit* otomatis (sesuai aturan proyek).

---

## 4. Pendekatan Arsitektur (Approaches)

Untuk **Poin 1 (Unduh PDF dengan teks yang dapat dicari & tampilan identik Cetak PDF)**, dua pendekatan telah dipertimbangkan:

| Pendekatan | Kelebihan | Kekurangan | Keputusan |
|---|---|---|---|
| **Opsi A: Server-Side Chrome Headless `--print-to-pdf` + Fallback `window.print()`** | Menghasilkan berkas `.pdf` vektor asli (teks 100% dapat di-*find*, mendukung `@page` portrait + `@page lanskap`, header/footer berjalan, dan SVG tajam) yang terunduh langsung dalam 1 klik. | Membutuhkan biner Google Chrome/Chromium di server (tersedia di `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`, dengan *fallback* otomatis ke `window.print()` jika tidak ada). | **Dipilih oleh Pengguna** |
| **Opsi B: Mengarahkan Tombol Unduh PDF ke `window.print()`** | Tanpa proses di sisi server. | Pengguna harus menekan tombol *Save as PDF* lagi di dalam dialog cetak browser (bukan unduhan berkas langsung). | Tidak dipilih |

---

## 5. Rincian Desain Teknis per Modul

### 5.1. Engine Unduh PDF Vektor RENOPS (Poin 1)

1. **Service Baru `services/RenopsPdfService.php`:**
   - Method `findChromeBinary(): ?string`: memeriksa path biner Chrome/Chromium (`getenv('CHROME_BIN')`, `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`, `/usr/bin/google-chrome`, `/usr/bin/chromium`, `/usr/bin/chromium-browser`).
   - Method `renderHtmlToPdf(string $html): ?string`:
     - Menulis `$html` (yang telah mengganti URL `BASE_URL . '/assets/'` dan `BASE_URL . '/uploads/'` menjadi `file://{ROOT_DIR}/assets/` dan `file://{ROOT_DIR}/uploads/`, serta menyembunyikan elemen `.no-print`) ke berkas sementara `/tmp/qrsar_renops_<random>.html`.
     - Menjalankan Chrome Headless dengan flag:
       `--headless --disable-gpu --no-sandbox --no-pdf-header-footer --virtual-time-budget=1500 --print-to-pdf=<tmpPdf> file://<tmpHtml>`
     - Mengembalikan isi biner PDF (`string`) jika berhasil dan valid (`str_starts_with($bytes, '%PDF-')`), atau `null` jika gagal/Chrome tidak tersedia.
     - Menghapus `<tmpHtml>` dan `<tmpPdf>` di dalam blok `finally`.
2. **Integrasi di `controllers/OperasiController.php`:**
   - Pada `renderRenopsDraf()`, `renderRenopsVersi()`, dan `renopsPublik()`:
     - Jika parameter `$_GET['format'] === 'pdf'` dikirim:
       - Tangkap output HTML dari `views/operasi/renops_smeac_print.php` menggunakan `ob_start()` / `ob_get_clean()`.
       - Panggil `RenopsPdfService::renderHtmlToPdf($html)`.
       - Jika berhasil, kirim header:
         - `Content-Type: application/pdf`
         - `Content-Disposition: attachment; filename="<sanitized_pdfName>.pdf"`
         - `Content-Length: <strlen>`
         - `Cache-Control: no-store`
         lalu cetak isi PDF dan `exit`.
       - Jika Chrome tidak tersedia (`null`), kembalikan status `HTTP 503` JSON `{"message": "Engine PDF vektor tidak tersedia, gunakan dialog cetak browser."}` agar klien otomatis melakukan *fallback* ke `window.print()`.
3. **Integrasi Klien di `views/operasi/renops_smeac_print.php` & `assets/js/pdf_document_exporter.js`:**
   - Di `views/operasi/renops_smeac_print.php`:
     - Memuat `sweetalert2.min.css` dan `sweetalert2.all.min.js` agar halaman cetak juga mendukung dialog SweetAlert2.
     - Mengubah label tombol `#btnDownloadPdf` dari `"PDF Gambar"` menjadi `"Unduh PDF"` dengan atribut `data-pdf-url` yang mengarah ke URL halaman saat ini ditambah `&format=pdf` (mis. `operasi.php?action=print_renops&id_renops=17&format=pdf` atau `operasi.php?action=renops_publik&token=...&format=pdf`).
     - Memastikan skrip QR Code (`QRCode.toCanvas`) berjalan segera saat DOM siap dan langsung menyalin ke `#qrImg` secara sinkron jika memungkinkan atau saat `logoImg.onload` selesai.
   - Di `assets/js/pdf_document_exporter.js`:
     - Pada `window.downloadOfficialPdf(customFilename, buttonEl)`:
       - Jika tombol atau halaman memiliki `data-pdf-url` (atau elemen `#renopsSmeacDoc` ada):
         - Lakukan `fetch(pdfUrl, { credentials: 'same-origin' })`.
         - Bila respons `res.ok` dan `Content-Type` mengandung `application/pdf`, unduh `Blob` tersebut secara langsung menggunakan `<a download="finalFilename">`.
         - Bila gagal atau `503`, lakukan *fallback* ke `window.print()` (dengan menyetel `document.title = finalFilename.replace(/\.pdf$/i, '')` terlebih dahulu) agar hasil tetap berupa PDF vektor yang dapat dicari.
       - Seluruh kode lama `html2pdf` di dalam `pdf_document_exporter.js` tetap dipertahankan sebagai *fallback* dokumen non-RENOPS dan demi menjaga kompatibilitas pengujian statis.

---

### 5.2. Drag-and-Drop Reorder Foto Dokumentasi Tanpa Tombol Manual (Poin 2)

1. **Perubahan Markup di `views/operasi/partials/renops_smeac_doc.php` & `views/operasi/renops_dokumen.php`:**
   - Memuat `assets/vendor/sortablejs/Sortable.min.js` pada `views/operasi/renops_dokumen.php`.
   - Menghapus kedua tombol manual `<button type="button" data-foto-geser="-1">▲</button>` dan `<button type="button" data-foto-geser="1">▼</button>` dari `.foto-ctl`.
   - Menambahkan atribut/gaya kursor geser (`cursor: grab`) dan indikator *drag handle* (`.foto-drag-handle` dengan ikon titik-titik grip `⋮⋮` di pojok kartu foto atau langsung mendrag gambar `figure.foto img`) pada mode draf (`$draft === true`).
2. **Perubahan Perilaku di `assets/js/pages/renops_dokumen.js`:**
   - Menginisialisasi `Sortable.create(grid, { ... })` pada setiap elemen `.foto-grid` dengan konfigurasi:
     - `group: 'renops-foto'` (memungkinkan foto ditarik lintas halaman lampiran 1 dan halaman lampiran 2).
     - `animation: 150`
     - `draggable: 'figure.foto'`
     - `filter: '.foto-ket, .foto-ctl, input, button, label'` dan `preventOnFilter: false` (agar klik pada *caption* `contenteditable`, *checkbox*, dan tombol hapus tetap berfungsi normal tanpa memicu *drag*).
   - Saat `onEnd` terpanggil:
     1. **Re-balancing Halaman (Maks. 6 Foto per `.foto-grid`):** Kumpulkan seluruh `figure.foto[data-id-foto]` sesuai urutan DOM terbaru, lalu distribusikan ulang ke dalam elemen-elemen `.foto-grid` yang ada sehingga halaman pertama berisi foto indeks `0..5` dan halaman kedua berisi foto indeks `6..11`.
     2. **Penomoran Ulang Caption (`Gambar X.`):** Hitung nomor awal gambar lampiran (berdasarkan jumlah gambar sebelum lampiran: peta + jaring komunikasi + struktur organisasi) dan perbarui teks nomor `Gambar X.` pada setiap `<figcaption class="cap-gambar">` secara langsung di DOM tanpa perlu *reload* halaman.
     3. **Simpan Urutan ke Server:** Kirim `PUT /api/renops/{id}/foto/reorder` dengan body `{ urutan_ids: [id1, id2, ...] }` (atau fallback `PUT /api/renops/{id}/foto/{idFoto}`). Tampilkan umpan balik status `"Urutan foto dokumentasi disimpan."` di `#renopsMsg` / toast.
3. **Endpoint API Reorder di `api/controllers/RenopsDokumenApiController.php` & `models/RenopsFotoModel.php`:**
   - Memperluas regex route di `RenopsDokumenApiController::handle()` menjadi:
     `#^/renops/(\d+)/(dokumen|foto)(?:/(terbit|reorder|bulk-delete|\d+))?\z#`
   - Menambahkan `RenopsFotoModel::reorder(int $idRenops, array $orderedIds): void` yang memperbarui kolom `urutan = 1..N` dalam satu transaksi database untuk seluruh foto milik `$idRenops`.

---

### 5.3. Bulk Delete Foto Dokumentasi (Poin 3)

1. **Antarmuka Bulk Delete di `views/operasi/partials/renops_smeac_doc.php`:**
   - Pada mode draf (`$draft === true`):
     - Di setiap `<figure class="foto" data-id-foto="...">`, tambahkan *checkbox* seleksi `<input type="checkbox" class="foto-select-cb" data-foto-pilih value="{id_foto}" aria-label="Pilih foto">` di sudut kiri atas gambar (di dalam `.no-print`) dan tombol hapus individual (`[data-foto-hapus]`) di sudut kanan atas.
     - Di bagian kontrol unggah foto pada lembar lampiran (`.no-print`), tambahkan bilah aksi **Manajemen Foto Dokumentasi** (tampil bila terdapat minimal 1 foto):
       - Checkbox / Tombol **Pilih Semua Foto** (`#cbPilihSemuaFoto`)
       - Indikator jumlah terpilih (`#labelFotoTerpilih`, mis. `0 foto dipilih`)
       - Tombol **Hapus Foto Terpilih** (`#btnHapusFotoTerpilih`, berwarna merah `bg-rose-600`, nonaktif saat 0 foto dipilih).
2. **Endpoint Bulk Delete di `api/controllers/RenopsDokumenApiController.php` & `models/RenopsFotoModel.php`:**
   - Endpoint `POST /api/renops/{id}/foto/bulk-delete` (atau `DELETE /api/renops/{id}/foto/bulk-delete`) menerima `{ ids: [int, ...] }`.
   - `RenopsFotoModel::deleteMany(int $idRenops, array $ids): int`:
     - Memvalidasi `$ids` hanya berisi bilangan bulat positif.
     - Mengambil daftar `path` dari `RenopsFoto WHERE id_renops = :r AND id_foto IN (...)`.
     - Menghapus baris dari `RenopsFoto` dalam transaksi, lalu memanggil `RenopsDokumenModel::deleteFiles($paths)` untuk menghapus berkas fisiknya.
     - Mengembalikan jumlah foto yang berhasil dihapus.
3. **Alur UX di `assets/js/pages/renops_dokumen.js`:**
   - Saat pengguna mencentang foto atau klik **Pilih Semua**, perbarui status tombol `#btnHapusFotoTerpilih` dan beri *highlight* border biru/oranye pada `figure.foto` yang terpilih.
   - Saat klik `#btnHapusFotoTerpilih`, tampilkan konfirmasi **SweetAlert2** (`"Hapus N Foto Terpilih?"`, `"Foto dokumentasi yang dihapus tidak dapat dikembalikan."`).
   - Jika dikonfirmasi, panggil API `POST /api/renops/{id}/foto/bulk-delete`, tampilkan notifikasi sukses, dan muat ulang halaman (atau hapus elemen dari DOM dan *re-balance* halaman).

---

### 5.4. Standardisasi Konfirmasi & Peringatan dengan SweetAlert2 (Poin 4)

Mengganti seluruh dialog bawaan browser (`window.confirm`, `confirm`, `window.alert`, `alert`) menjadi modal **SweetAlert2** (`Swal.fire` / `confirmAction` / `showErrorAlert`):

1. **`assets/js/pages/renops_dokumen.js`:**
   - `fail(e)`: Menggunakan `showErrorAlert('Terjadi Kesalahan', e.message)` (atau `Swal.fire({ icon: 'error', ... })`) menggantikan `window.alert(e.message)`.
   - Validasi batas foto (> 12 foto, melebihi sisa slot, atau ukuran > 5 MB): Menggunakan `showErrorAlert('Unggahan Ditolak', pesan)` menggantikan `window.alert(pesan)`.
   - `reset(key)`: Menggunakan `await confirmAction('Reset Bagian Ini?', 'Ada suntingan yang belum disimpan dan akan hilang. Lanjutkan reset?', 'Ya, Reset', true)` menggantikan `window.confirm(...)`.
   - `#btnTerbitkan`: Menggunakan `await confirmAction('Terbitkan Versi Baru?', "Terbitkan versi baru? QR versi sebelumnya akan berstatus 'telah diubah'.", 'Ya, Terbitkan QR', false)` menggantikan `window.confirm(...)`.
   - `[data-foto-hapus]` (hapus 1 foto): Menggunakan `await confirmAction('Hapus Foto Dokumentasi?', 'Hapus foto ini dari dokumen Rencana Operasi?', 'Ya, Hapus Foto', true)` menggantikan `window.confirm('Hapus foto ini?')`.
   - `#btnHapusFotoTerpilih` (bulk delete foto): Menggunakan `await confirmAction(...)`.
2. **`assets/js/pdf_document_exporter.js` & `views/operasi/renops_smeac_print.php`:**
   - Memastikan `sweetalert2.all.min.js` dimuat di `views/operasi/renops_smeac_print.php`.
   - Mengganti `alert(...)` di `pdf_document_exporter.js` dengan helper berbasis `Swal.fire` (dengan fallback aman jika `Swal` belum termuat).
3. **`assets/js/pages/operasi_detail.js`:**
   - Mengganti `confirm('Buka kembali operasi SAR ini menjadi status OPEN?...')` pada baris 690 dengan `await confirmAction(...)`.
   - Memastikan urutan pemuatan skrip di `views/operasi/renops_dokumen.php` atau inisialisasi di `renops_dokumen.js` dapat mengakses `window.Swal`, `confirmAction`, dan `showErrorAlert` yang dimuat di `views/layout/footer.php`.
     - *Catatan Penting Urutan Skrip:* Di `views/operasi/renops_dokumen.php` baris 71, `<script src=".../renops_dokumen.js"></script>` dieksekusi di akhir *view*, sedangkan `sweetalert2.all.min.js` dan `app.js` dimuat setelahnya di `views/layout/footer.php`! Karena handler klik (`reset`, `btnTerbitkan`, `hapus`, `fail`) dipanggil secara asinkron saat pengguna berinteraksi (setelah seluruh halaman dan `footer.php` selesai dimuat), `window.Swal`, `window.confirmAction`, dan `window.showErrorAlert` sudah tersedia di `window`. Namun untuk ketahanan penuh, `renops_dokumen.js` juga akan menyediakan wrapper internal berbasis `window.Swal` / `window.confirmAction` / `window.showErrorAlert`.

