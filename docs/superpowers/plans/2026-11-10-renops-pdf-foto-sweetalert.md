# Optimasi PDF Vektor RENOPS, Drag-Reorder & Bulk Delete Foto Dokumentasi, serta Standardisasi SweetAlert2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyeragamkan hasil Unduh PDF RENOPS agar 100% identik dengan Cetak PDF (vektor & teks dapat dicari via Chrome Headless `--print-to-pdf` + fallback `window.print()`), mengganti tombol urutan manual foto dokumentasi dengan *drag-and-drop* (`SortableJS`) lintas lembar lampiran, menyediakan fitur *bulk delete* foto dokumentasi, serta mengganti seluruh dialog konfirmasi/peringatan bawaan browser (`localhost`) dengan **SweetAlert2**.

**Architecture:**
1. Menambahkan `RenopsPdfService` di `services/RenopsPdfService.php` untuk merender HTML cetak RENOPS menjadi berkas PDF vektor menggunakan Google Chrome Headless (`--headless --print-to-pdf`), diintegrasikan ke `OperasiController` (`&format=pdf`), `renops_smeac_print.php`, dan `pdf_document_exporter.js`.
2. Menambahkan operasi *batch reorder* (`RenopsFotoModel::reorder`) dan *batch delete* (`RenopsFotoModel::deleteMany`) di model dan `RenopsDokumenApiController`.
3. Memperbarui `views/operasi/partials/renops_smeac_doc.php`, `views/operasi/renops_dokumen.php`, dan `assets/js/pages/renops_dokumen.js` untuk mendukung *drag-and-drop* `SortableJS` lintas halaman lampiran (maks. 6 foto/halaman), *checkbox* seleksi & *bulk delete*, serta mengganti seluruh `window.confirm` / `window.alert` dengan modal **SweetAlert2** (tanpa menggunakan `innerHTML` di `renops_dokumen.js`).

**Tech Stack:** PHP 8.x (PDO MySQL, GD), Vanilla JavaScript (ES6+), SortableJS, SweetAlert2, Google Chrome Headless CLI, Tailwind CSS.

**Spec:** `docs/superpowers/specs/2026-11-10-renops-pdf-foto-sweetalert-design.md`

## Global Constraints

- Dilarang melakukan `git commit` otomatis pada tahap mana pun (pengguna melakukan *commit* secara manual).
- Seluruh teks UI dan pesan galat wajib menggunakan Bahasa Indonesia formal.
- Di dalam `assets/js/pages/renops_dokumen.js`, dilarang menggunakan properti `innerHTML` atau `insertAdjacentHTML` (diuji secara ketat oleh `tests/test_renops_dokumen_editor.php` baris 37).
- Setiap halaman lampiran foto (`.hal-lampiran-foto .foto-grid`) maksimal menampung 6 foto (`array_chunk(..., 6, true)` di PHP dan *re-balancing* DOM saat *drag-and-drop* di JS).
- Seluruh operasi `reorder` dan `bulk-delete` wajib memverifikasi kepemilikan foto terhadap `id_renops` (`WHERE id_renops = :r AND id_foto = :i`).
- Seluruh 81 *test suite* PHP (`tests/run_all.php`) harus lulus 100%.

## Review Focus

1. **ID Foto Milik RENOPS Lain pada Reorder / Bulk Delete:** Jika `PUT /api/renops/{id}/foto/reorder` atau `POST /api/renops/{id}/foto/bulk-delete` dikirim dengan ID foto milik dokumen RENOPS lain, foto dokumen lain tersebut tidak boleh berubah urutannya atau terhapus.
2. **Drag-and-Drop Lintas Lembar Lampiran (> 6 Foto):** Jika dokumen memiliki 8 foto (6 di halaman lampiran 1, 2 di halaman lampiran 2) lalu 1 foto ditarik dari halaman 2 ke halaman 1, DOM wajib otomatis memindahkan foto ke-7 dari halaman 1 ke halaman 2 sehingga halaman 1 tetap maksimal 6 foto dan penomoran `Gambar X.` berurutan dari awal hingga akhir.
3. **Interaksi Caption & Checkbox Saat SortableJS Aktif:** Mengklik teks keterangan foto (`.foto-ket[contenteditable]`), mencentang *checkbox* pilih foto (`[data-foto-pilih]`), atau menekan tombol hapus (`[data-foto-hapus]`) tidak boleh terhalang oleh event *drag* `SortableJS`.
4. **Pembersihan Berkas Sementara PDF Vektor:** `RenopsPdfService::renderHtmlToPdf()` wajib selalu menghapus berkas `.html` dan `.pdf` sementara di `sys_get_temp_dir()` baik ketika berhasil maupun ketika terjadi kegagalan.
5. **Larangan `innerHTML` di `renops_dokumen.js` & Zero `window.confirm`/`window.alert`:** Seluruh manipulasi DOM dan pemanggilan SweetAlert2 di `renops_dokumen.js` wajib bebas dari `innerHTML`, `insertAdjacentHTML`, `window.confirm`, dan `window.alert`.

---

### Task 1: Backend API & Model untuk Batch Reorder dan Bulk Delete Foto Dokumentasi (Poin 2 & 3)

**Files:**
- Modify: `models/RenopsFotoModel.php`
- Modify: `api/controllers/RenopsDokumenApiController.php`
- Test: `tests/test_renops_foto_upload.php`
- Test: `tests/test_renops_dokumen_api.php`

**Interfaces:**
- Produces:
  - `RenopsFotoModel::reorder(int $idRenops, array $orderedIds): void`
  - `RenopsFotoModel::deleteMany(int $idRenops, array $ids): int`
  - `PUT /api/renops/{id}/foto/reorder` menerima JSON `{"ids": [int, ...]}` -> `{"success": true, "foto": [...]}`
  - `POST /api/renops/{id}/foto/bulk-delete` menerima JSON `{"ids": [int, ...]}` -> `{"success": true, "deleted": int, "foto": [...]}`

- [ ] **Step 1: Tambahkan pengujian gagal (failing tests) untuk `reorder` dan `deleteMany` di `tests/test_renops_foto_upload.php` dan `tests/test_renops_dokumen_api.php`**

Tambahkan pengujian di `tests/test_renops_foto_upload.php` untuk:
1. `RenopsFotoModel::reorder($idR2, $reversedIds)` membalik urutan 12 foto menjadi `1..12` sesuai urutan array, dan mengabaikan ID foto milik RENOPS lain.
2. `RenopsFotoModel::deleteMany($idR2, [$id1, $id2, 999999])` menghapus tepat 2 foto milik `$idR2`, menghapus berkas fisiknya dari disk, dan mengembalikan `2`.

Tambahkan pengujian di `tests/test_renops_dokumen_api.php` (bagian `1c. Handler API`) untuk:
1. `runApi("/renops/{$rY}/foto/reorder", 'PUT', ['ids' => [...]], $sess)` mengembalikan status `200` dan memperbarui urutan.
2. `runApi("/renops/{$rY}/foto/bulk-delete", 'POST', ['ids' => [...]], $sess)` mengembalikan status `200` dan menghapus foto terkait.

- [ ] **Step 2: Jalankan test untuk memastikan gagal sebelum implementasi**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_foto_upload.php`
Expected: FAIL karena `RenopsFotoModel::reorder` dan `RenopsFotoModel::deleteMany` belum ada.

- [ ] **Step 3: Implementasikan `RenopsFotoModel::reorder()` dan `RenopsFotoModel::deleteMany()` di `models/RenopsFotoModel.php` serta routing di `api/controllers/RenopsDokumenApiController.php`**

1. Di `models/RenopsFotoModel.php`:
   - `public static function reorder(int $idRenops, array $orderedIds): void`:
     Dalam transaksi DB, ambil semua `id_foto` milik `$idRenops` (`ORDER BY urutan, id_foto`). Susun urutan baru dengan menempatkan ID valid dari `$orderedIds` terlebih dahulu (tanpa duplikat), diikuti sisa ID milik `$idRenops` yang belum tercantum, lalu `UPDATE RenopsFoto SET urutan = :u WHERE id_foto = :i AND id_renops = :r` dengan `urutan = 1..N`.
   - `public static function deleteMany(int $idRenops, array $ids): int`:
     Saring `$ids` menjadi integer positif unik. Jika kosong, kembalikan `0`. Dalam transaksi DB, query `SELECT id_foto, path FROM RenopsFoto WHERE id_renops = :r AND id_foto IN (...) FOR UPDATE`. Hapus baris yang cocok (`DELETE FROM RenopsFoto WHERE id_renops = :r AND id_foto IN (...)`), `commit()`, lalu panggil `RenopsDokumenModel::deleteFiles($paths)` dan kembalikan jumlah baris terhapus.
2. Di `api/controllers/RenopsDokumenApiController.php`:
   - Perbarui regex di `handle()` menjadi:
     `if (!preg_match('#^/renops/(\d+)/(dokumen|foto)(?:/(terbit|reorder|bulk-delete|\d+))?\z#', $path, $m))`
   - Untuk `$m[2] === 'foto'`:
     - Jika `$sub === 'reorder' && $method === 'PUT'`: validasi `$input['ids']` adalah array, panggil `RenopsFotoModel::reorder($id, $input['ids'])`, kembalikan `self::json(['success' => true, 'foto' => RenopsFotoModel::listByRenops($id)])`.
     - Jika `$sub === 'bulk-delete' && ($method === 'POST' || $method === 'DELETE')`: validasi `$input['ids']` adalah array tidak kosong, panggil `$deleted = RenopsFotoModel::deleteMany($id, $input['ids'])`, kembalikan `self::json(['success' => true, 'deleted' => $deleted, 'foto' => RenopsFotoModel::listByRenops($id)])`.

- [ ] **Step 4: Jalankan test untuk memastikan lulus**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_foto_upload.php && DB_DATABASE=qrsar_test php tests/test_renops_dokumen_api.php`
Expected: PASS 100%.

---

### Task 2: Engine Unduh PDF Vektor RENOPS via Chrome Headless + Fallback `window.print()` (Poin 1 & Poin 4 di Exporter)

**Files:**
- Create: `services/RenopsPdfService.php`
- Modify: `controllers/OperasiController.php`
- Modify: `views/operasi/renops_smeac_print.php`
- Modify: `assets/js/pdf_document_exporter.js`
- Test: `tests/test_renops_smeac_render.php`
- Test: `tests/test_renops_dokumen_verify.php`

**Interfaces:**
- Produces:
  - `RenopsPdfService::findChromeBinary(): ?string`
  - `RenopsPdfService::prepareHtmlForLocalRender(string $html): string`
  - `RenopsPdfService::renderHtmlToPdf(string $html): ?string`
  - Query param `&format=pdf` pada `operasi.php?action=print_renops` dan `operasi.php?action=renops_publik`

- [ ] **Step 1: Tambahkan pengujian di `tests/test_renops_smeac_render.php` dan `tests/test_renops_dokumen_verify.php`**

1. Di `tests/test_renops_smeac_render.php`:
   - Uji `RenopsPdfService::prepareHtmlForLocalRender($html)` mengubah URL `BASE_URL . '/assets/...'` dan `BASE_URL . '/uploads/...'` menjadi `file://` lokal di dalam `ROOT_DIR`.
   - Bila `RenopsPdfService::findChromeBinary() !== null`, uji `RenopsPdfService::renderHtmlToPdf($pub)` menghasilkan string PDF valid yang diawali `%PDF-` dan berukuran > 5000 byte.
   - Pastikan halaman cetak (`renops_smeac_print.php`) memuat `sweetalert2.all.min.js` dan tombol `#btnDownloadPdf` memiliki `data-pdf-url`.
2. Di `tests/test_renops_dokumen_verify.php`:
   - Uji `runAction(['action' => 'print_renops', 'id_renops' => $idR, 'format' => 'pdf'], $login)` mengembalikan `200` dan body diawali `%PDF-` (bila Chrome tersedia).

- [ ] **Step 2: Jalankan test untuk memastikan gagal sebelum implementasi**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_smeac_render.php`
Expected: FAIL karena `RenopsPdfService` belum ada.

- [ ] **Step 3: Buat `services/RenopsPdfService.php` dan integrasikan ke `OperasiController.php`, `renops_smeac_print.php`, serta `pdf_document_exporter.js`**

1. Buat `services/RenopsPdfService.php`:
   - `findChromeBinary(): ?string`: memeriksa `getenv('CHROME_BIN')`, `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`, `/usr/bin/google-chrome`, `/usr/bin/google-chrome-stable`, `/usr/bin/chromium`, `/usr/bin/chromium-browser`.
   - `prepareHtmlForLocalRender(string $html): string`:
     Mengganti atribut `src="..."` dan `href="..."` yang diawali `BASE_URL . '/assets/'` atau `BASE_URL . '/uploads/'` (termasuk query string `?v=...`) menjadi URI `file://` absolut ke `ROOT_DIR . '/assets/...'` atau `ROOT_DIR . '/uploads/...'`. Menyuntikkan `<style>.no-print, .no-print * { display: none !important; } html, body { background: #ffffff !important; } .page-sheet.renops-smeac { zoom: 1 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; width: auto !important; min-height: 0 !important; }</style>` sebelum `</head>`.
   - `renderHtmlToPdf(string $html): ?string`:
     Membuat file sementara `/tmp/qrsar_renops_<hex>.html` dan `/tmp/qrsar_renops_<hex>.pdf`, mengeksekusi Chrome dengan `--headless --disable-gpu --no-sandbox --no-pdf-header-footer --virtual-time-budget=1500 --print-to-pdf=...`, membaca hasil PDF jika valid (`str_starts_with($pdf, '%PDF-')`), dan selalu menghapus kedua file sementara di `finally`.
2. Di `controllers/OperasiController.php`:
   - Tambahkan helper `private function renderRenopsPrintView(array $viewVars): void` yang digunakan oleh `renderRenopsDraf()` dan `renderRenopsVersi()`.
   - Jika `($_GET['format'] ?? '') === 'pdf'`, tangkap HTML dari `VIEWS_DIR . '/operasi/renops_smeac_print.php'`, panggil `RenopsPdfService::renderHtmlToPdf($html)`, dan kirim respons biner PDF (`application/pdf`, `Content-Disposition: attachment; filename="..."`) atau `503` JSON jika Chrome tidak tersedia.
3. Di `views/operasi/renops_smeac_print.php`:
   - Bangun `$pdfDownloadUrl` dari URL aksi saat ini (`renops_publik` dengan `token`, atau `print_renops` dengan `id_renops` & `draf=1` jika mode pratinjau) ditambah `&format=pdf`.
   - Tambahkan `data-pdf-url="<?= e($pdfDownloadUrl) ?>"` pada `#btnDownloadPdf`, dan ubah teks tombol menjadi `Unduh PDF`.
   - Muat `assets/vendor/sweetalert2/sweetalert2.min.css` dan `assets/vendor/sweetalert2/sweetalert2.all.min.js`.
4. Di `assets/js/pdf_document_exporter.js`:
   - Tambahkan helper internal `notifyPdfError(msg)` yang menggunakan `window.Swal.fire({ icon: 'error', title: 'Gagal Mengunduh PDF', text: msg, confirmButtonColor: '#ea580c' })` menggantikan `alert(...)`.
   - Di awal `window.downloadOfficialPdf = function (customFilename, buttonEl)`:
     Jika `btn && btn.getAttribute('data-pdf-url')` tersedia, unduh PDF vektor dari `data-pdf-url` via `fetch(pdfUrl, { credentials: 'same-origin' })`. Jika respons adalah `application/pdf`, picu unduhan `Blob` dengan nama `finalFilename`. Jika server mengembalikan `503` (Chrome tidak tersedia), lakukan *fallback* ke `window.print()` dengan menyetel `document.title` ke nama file. Pertahankan kode `html2pdf` di bawahnya agar dokumen lain & pengujian statis tetap kompatibel.

- [ ] **Step 4: Jalankan test untuk memastikan lulus**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_smeac_render.php && DB_DATABASE=qrsar_test php tests/test_renops_dokumen_verify.php && DB_DATABASE=qrsar_test php tests/test_audit_modul_5_verify_qr_pdf.php && DB_DATABASE=qrsar_test php tests/test_doc_verify_and_barcode.php`
Expected: PASS 100%.

---

### Task 3: Drag-and-Drop Reorder Foto, Bulk Delete UI, & Standardisasi SweetAlert2 di Editor RENOPS (Poin 2, 3, 4)

**Files:**
- Modify: `views/operasi/renops_dokumen.php`
- Modify: `views/operasi/partials/renops_smeac_doc.php`
- Modify: `assets/js/pages/renops_dokumen.js`
- Modify: `assets/js/pages/operasi_detail.js`
- Test: `tests/test_renops_dokumen_editor.php`
- Test: `tests/test_renops_smeac_render.php`

**Interfaces:**
- Consumes:
  - `PUT /api/renops/{id}/foto/reorder` (`{"ids": [...]}`)
  - `POST /api/renops/{id}/foto/bulk-delete` (`{"ids": [...]}`)
  - `Sortable` (`assets/vendor/sortablejs/Sortable.min.js`)
  - `window.Swal`, `window.confirmAction`, `window.showErrorAlert` (`assets/js/app.js`)

- [ ] **Step 1: Perbarui pengujian di `tests/test_renops_dokumen_editor.php` sesuai spesifikasi baru**

Perbarui `tests/test_renops_dokumen_editor.php` untuk memeriksa:
1. `renops_dokumen.php` memuat `assets/vendor/sortablejs/Sortable.min.js`.
2. `renops_smeac_doc.php` tidak lagi merender tombol manual `data-foto-geser`, melainkan merender *drag handle* (`data-foto-drag`), *checkbox* pilih foto (`data-foto-pilih`), tombol hapus individual (`data-foto-hapus`), serta kontrol *bulk delete* (`id="cbPilihSemuaFoto"` dan `id="btnHapusFotoTerpilih"`).
3. `renops_dokumen.js` menginisialisasi `Sortable` pada `.foto-grid`, memanggil `/foto/reorder` dan `/foto/bulk-delete`, melakukan *re-balancing* maksimal 6 foto per `.foto-grid`, serta **tidak mengandung** `window.confirm`, `window.alert`, `innerHTML`, atau `insertAdjacentHTML`.

- [ ] **Step 2: Jalankan test untuk melihat kegagalan awal**

Run: `DB_DATABASE=qrsar_test php tests/test_renops_dokumen_editor.php`
Expected: FAIL pada assertion baru.

- [ ] **Step 3: Implementasikan perubahan pada `renops_dokumen.php`, `renops_smeac_doc.php`, `renops_dokumen.js`, dan `operasi_detail.js`**

1. Di `views/operasi/renops_dokumen.php`:
   - Muat `<script src="<?= assetUrl('assets/vendor/sortablejs/Sortable.min.js') ?>"></script>` sebelum `renops_dokumen.js`.
2. Di `views/operasi/partials/renops_smeac_doc.php`:
   - Tambahkan gaya CSS untuk `.foto-select-wrap`, `.foto-drag-handle`, `figure.foto.is-selected`, dan `.foto-sortable-ghost`.
   - Di dalam `<figure class="foto" data-id-foto="...">` (mode `$draft`):
     - Ganti tombol `data-foto-geser="-1"` dan `data-foto-geser="1"` dengan:
       - Checkbox pilih foto di kiri atas: `<label class="foto-select-wrap no-print" title="Pilih foto untuk hapus massal"><input type="checkbox" class="foto-select-cb" data-foto-pilih value="<?= (int)($f['id_foto'] ?? 0) ?>" aria-label="Pilih foto"><span>Pilih</span></label>`
       - Di `.foto-ctl.no-print` kanan atas: ikon *drag handle* `<span class="foto-drag-handle" data-foto-drag title="Tarik untuk mengubah urutan foto" aria-label="Tarik untuk mengubah urutan foto">&#8942;&#8942;</span>` dan tombol hapus `<button type="button" data-foto-hapus title="Hapus foto" aria-label="Hapus foto">...</button>`.
     - Pada `<figcaption class="cap-gambar">`, bungkus teks `"Gambar X. "` dalam `<span class="foto-num">Gambar <?= ++$nGambar ?>. </span>` agar nomor gambar dapat diperbarui secara presisi dengan `textContent` saat *drag-and-drop* tanpa menyentuh `<span class="foto-ket">`.
   - Di bawah `.foto-grid` pada halaman lampiran terakhir (`$draft && $isLastFotoPage`):
     - Tambahkan bilah kontrol *bulk delete* (`.foto-bulk-bar.no-print`, tersembunyi jika `$totalFotos === 0`):
       - `<label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer"><input type="checkbox" id="cbPilihSemuaFoto"> <span>Pilih Semua Foto (<span id="countFotoTerpilih">0</span> terpilih)</span></label>`
       - `<button type="button" id="btnHapusFotoTerpilih" disabled class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 disabled:opacity-40 text-white rounded-lg font-bold text-xs cursor-pointer">Hapus Foto Terpilih</button>`
3. Di `assets/js/pages/renops_dokumen.js`:
   - Ganti `fail(e)` dan seluruh `window.alert` dengan helper `swalError(title, text)` yang memanggil `window.showErrorAlert` atau `window.Swal.fire`.
   - Buat helper `swalConfirm(title, text, confirmText, isDanger)` yang memanggil `window.confirmAction` atau `window.Swal.fire`.
   - Ganti seluruh `window.confirm` pada `reset()`, `#btnTerbitkan`, dan `[data-foto-hapus]` menjadi `await swalConfirm(...)`.
   - Implementasikan fungsi `rebalanceAndRenumberFotos()`:
     - Mengumpulkan semua `figure.foto[data-id-foto]` dari seluruh `.foto-grid`, membagi ulang ke dalam `.foto-grid` maksimal 6 item per grid (`grid.append(fig)`), dan memperbarui `.foto-num` (`textContent = 'Gambar ' + (baseGambar + idx + 1) + '. '`).
   - Inisialisasi `Sortable` pada semua `.foto-grid` (`group: 'renops-foto'`, `animation: 150`, `draggable: 'figure.foto'`, `filter: '.foto-ket, input, button, label'`, `preventOnFilter: false`, `ghostClass: 'foto-sortable-ghost'`). Pada `onEnd`, jalankan `rebalanceAndRenumberFotos()` dan kirim `await api('PUT', '/foto/reorder', { ids })`.
   - Implementasikan logika seleksi `data-foto-pilih`, `#cbPilihSemuaFoto`, dan `#btnHapusFotoTerpilih` (dengan konfirmasi `await swalConfirm(...)` lalu `await api('POST', '/foto/bulk-delete', { ids })`).
   - Pastikan tidak ada satu pun penggunaan `innerHTML`, `insertAdjacentHTML`, `window.confirm`, atau `window.alert`.
4. Di `assets/js/pages/operasi_detail.js`:
   - Ganti `if (confirm('Buka kembali operasi SAR ini menjadi status OPEN?...'))` pada baris 690 dengan `if (await confirmAction('Buka Kembali Operasi SAR?', 'Buka kembali operasi SAR ini menjadi status OPEN? Perhitungan hari operasional akan dimulai lagi.', 'Ya, Buka Kembali', false))`.

- [ ] **Step 4: Jalankan test untuk memastikan lulus**

Run: `node -c assets/js/pages/renops_dokumen.js && node -c assets/js/pdf_document_exporter.js && node -c assets/js/pages/operasi_detail.js && DB_DATABASE=qrsar_test php tests/test_renops_dokumen_editor.php && DB_DATABASE=qrsar_test php tests/test_renops_smeac_render.php`
Expected: PASS 100%.

---

### Task 4: Verifikasi Regresi Menyeluruh (81 PHP Test Suites & 6 JS Test Suites)

**Files:**
- Modify: `/Users/irfandydk/.gemini/antigravity/brain/b17c089f-b190-4609-9e44-97a74a2a9525/task-progress.md`
- Test: `tests/run_all.php`

- [ ] **Step 1: Jalankan seluruh JS unit tests**

Run: `for f in tests/js/*.test.js; do node "$f" || exit 1; done`
Expected: PASS 100%.

- [ ] **Step 2: Jalankan seluruh 81 PHP test suites**

Run: `DB_DATABASE=qrsar_test php tests/run_all.php`
Expected: `Total Suite Pengujian : 81, Lulus (PASS) : 81, Gagal (FAIL) : 0`.

