# Rencana Implementasi Pusat Dokumentasi Umum & Operasi SAR

> **Alur Eksekusi:** TDD ketat (tulis tes gagal -> implementasi minimal & bersih -> verifikasi hijau penuh -> uji visual browser).

**Goal:** Membangun menu sidebar `/dokumentasi.php` untuk pembuatan, pengeditan ulang penuh, dan pengarsipan kronologis dokumentasi kolase umum maupun operasi SAR aktif dengan proteksi hapus khusus Admin.

**Tech Stack:** PHP 8.x MVC Native, MySQL/MariaDB (`OperasiDokumentasi`), HTML5 Canvas + MediaRecorder Studio (`operasi_dokumentasi.js`), Tailwind CSS v3.

---

### Task 1: Suite Pengujian Otomatis TDD (`tests/test_dokumentasi_umum_dan_arsip.php`)
- Uji kolom `OperasiDokumentasi.id_operasi` mengizinkan `NULL`.
- Uji `storeSourceAsset(null, ...)`, `store(null, ...)`, `getAllWithOperasi()` (beserta filter `scope`, `media_type`, `month`, `q`), `getArchiveStats()`, dan `getAvailableMonths()`.
- Uji round-trip `update()` untuk dokumentasi umum dan perpindahan kaitan antara `NULL` <-> Operasi SAR aktif tanpa kehilangan aset sumber `src_0_*`.
- Uji isolasi penghapusan `OperasiModel::delete($opsId)` agar dokumentasi umum (`id_operasi IS NULL`) beserta berkas fisiknya tidak ikut terhapus.
- Uji RBAC Opsi 1 (`AuthHelper::canDelete('dokumentasi')` false untuk `USER`, true untuk `ADMIN`, guard `403` di API, serta guard tombol hapus di view).
- Uji kontrak rute `Router.php`, `api/index.php`, `sidebar.php`, `DokumentasiController.php`, dan penamaan berkas unduhan kolase umum di `collage_layouts.js`.

### Task 2: Migrasi Database, Model, REST API, & RBAC Hapus
- Buat `database/migrations/2026_11_24_01_allow_nullable_id_operasi_dokumentasi.sql` (`MODIFY COLUMN id_operasi INT NULL DEFAULT NULL` + indeks kronologis `idx_ops_dok_created_at`).
- Perbarui `OperasiDokumentasiModel.php`:
  - `ensureSchemaUpdated()` membuat/mengubah `id_operasi INT NULL DEFAULT NULL` dan indeks `idx_ops_dok_created_at` secara aman di luar transaksi.
  - Perluas `storeSourceAsset(?int $idOperasi, ...)`, `store(?int $idOperasi, ...)`, `update(int $idDokumentasi, ?int $idOperasi, ..., ?int $expectedOperasiId = null)`, `delete(...)`, `sanitizeProjectJson()`, `filterUnreferencedSourcePaths()`, dan `collectFilePathsForOperasi()`.
  - Tambahkan `getAllWithOperasi(array $filters = [])`, `getArchiveStats()`, dan `getAvailableMonths()`.
- Perbarui `api/index.php` dan `api/controllers/OperasiDokumentasiApiController.php`:
  - Dukung `/dokumentasi`, `/dokumentasi/source`, dan `/dokumentasi/{docId}`.
  - Terapkan `AuthHelper::canDelete('dokumentasi')` (HTTP `403`) pada seluruh operasi `DELETE`.

### Task 3: Controller, Menu Sidebar, Halaman Arsip Kronologis, & Studio Umum/Operasi
- Perbarui `helpers/Router.php` dan `helpers/ViewHelper.php` untuk rute `/dokumentasi`, `/dokumentasi/create`, `/dokumentasi/edit`.
- Buat `dokumentasi.php` dan `controllers/DokumentasiController.php` (`index()` untuk arsip kronologis per bulan/tahun dan `studio()` untuk pembuatan/pengeditan kolase dengan daftar operasi SAR aktif).
- Tambahkan item navigasi **Dokumentasi** di `views/layout/sidebar.php` tepat di bawah **BRA SAR**.
- Buat `views/dokumentasi/index.php` (kartu metrik filter, pencarian, filter media & bulan, grup kronologis Bulan-Tahun, kartu arsip dengan `Lihat`, `Edit`, `Unduh`, dan `Hapus` khusus Admin) serta kunci tombol hapus di `views/operasi/partials/tab_dokumentasi.php`.
- Perbarui `views/operasi/dokumentasi_studio.php`, `assets/js/pages/operasi_dokumentasi.js`, dan `assets/js/dokumentasi/collage_layouts.js` agar mendukung dropdown **Kaitan Operasi SAR (Opsional)**, kicker *Editorial Modern* dinamis (`DOKUMENTASI • ...` vs `DOKUMENTASI OPERASI SAR • ...`), dan nama berkas unduhan `DOK_KEGIATAN_...` untuk dokumentasi umum.

### Task 4: Migrasi, Build Tailwind, Graphify, Suite Tes Penuh, & Verifikasi Browser
- Jalankan migrasi pada `qrsar` dan `qrsar_test`, build ulang `assets/css/tailwind.css`, dan jalankan `graphify update .`.
- Jalankan tes JS Node (`tests/js/*.test.js`) dan seluruh suite PHP (`DB_DATABASE=qrsar_test php tests/run_all.php`).
- Verifikasi langsung di Chrome pada `http://127.0.0.1:8000/dokumentasi.php` dan Studio pembuatan/edit kolase umum maupun operasi SAR aktif.
