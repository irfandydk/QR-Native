# Desain Pusat Dokumentasi Umum & Operasi SAR (`/dokumentasi.php`)

**Tanggal:** 24 November 2026
**Proyek:** QR-SAR — Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari

## 1. Tujuan & Ruang Lingkup
Menyediakan menu **Dokumentasi** tersendiri pada sidebar navigasi utama (`/dokumentasi.php`) yang berfungsi sebagai:
1. **Pusat Arsip Kronologis Terpadu:** Menampilkan seluruh dokumentasi kolase (baik **Dokumentasi Umum** tanpa kaitan operasi SAR maupun **Dokumentasi Operasi SAR**) yang tersusun rapi berdasarkan waktu pembuatannya (dikelompokkan per Bulan & Tahun, terbaru di atas).
2. **Studio Pembuatan & Pengeditan Terpadu:** Menggunakan engine kolase resmi KPP Kelas A Kendari yang sama persis (2 gaya desain: *Klasik Resmi KPP* dan *Editorial Modern*, 4-12 slot foto/video, orientasi Vertikal/Horizontal/Persegi, pratinjau interaktif langsung, serta ekspor Full HD / 2K QHD / 4K Ultra HD).
3. **Alternatif Pemilihan Operasi SAR Aktif:** Pada Studio halaman Dokumentasi, pengguna dapat memilih default **Dokumentasi Umum (Tanpa Operasi SAR)** ataupun mengaitkannya langsung ke salah satu **Operasi SAR Aktif (`OPEN`)** tanpa harus masuk ke halaman detail operasi terlebih dahulu.
4. **Kontrol Akses Penghapusan (Opsi 1 RBAC):** Seluruh arsip dapat dibuat, diunduh, dan diedit ulang oleh Operator (`USER`) maupun Administrator (`ADMIN`), namun **penghapusan arsip dikunci khusus `ADMIN`** (`USER` tidak melihat tombol hapus dan ditolak `403 Forbidden` di sisi server).

## 2. Arsitektur Data (Pendekatan A — Unified Table)
Memperluas tabel `OperasiDokumentasi` agar mendukung `id_operasi NULL`:
- `id_operasi INT NULL DEFAULT NULL`
  - `NULL` = **Dokumentasi Umum** (latihan gabungan, apel siaga, kunjungan kerja, sosialisasi SAR, giat kantor). Aman dari `ON DELETE CASCADE` ketika suatu `Operasi` dihapus.
  - `> 0` = **Dokumentasi Operasi SAR** (tetap terhubung melalui foreign key `fk_ops_dok_operasi` ke `Operasi(id_operasi)`).
- Penamaan berkas fisik di `uploads/operasi_dokumentasi/`:
  - Dokumentasi Umum memakai segmen ID `0` (`dok_0_...`, `thumb_0_...`, `src_0_...`).
  - `sanitizeProjectJson()` memvalidasi pola `uploads/operasi_dokumentasi/src_\d+_[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp|webm|mp4)` beserta keberadaan berkas di disk agar kolase tetap aman saat pengguna mengganti kaitan antara Dokumentasi Umum dan Operasi SAR Aktif.
  - `filterUnreferencedSourcePaths()` memeriksa referensi `project_json` lintas seluruh baris `OperasiDokumentasi` sebelum menghapus aset sumber yatim.

## 3. Endpoint REST API & Keamanan RBAC
- Menambahkan rute `/api/dokumentasi`, `/api/dokumentasi/source`, dan `/api/dokumentasi/{docId}` pada `api/index.php` yang diteruskan ke `OperasiDokumentasiApiController`, berdampingan dengan rute `/api/operasi/{id}/dokumentasi...` yang tetap dipertahankan 100% kompatibel.
- Seluruh permintaan `DELETE` wajib lulus `AuthHelper::canDelete('dokumentasi')` (hanya `ADMIN`). Akun `USER` menerima respons HTTP `403` JSON.

## 4. Antarmuka Pengguna
- **Sidebar (`views/layout/sidebar.php`):** Menu **Dokumentasi** ditempatkan tepat di bawah **BRA SAR**.
- **Halaman Arsip (`views/dokumentasi/index.php`):**
  - 4 kartu metrik ringkas yang berfungsi sebagai filter cakupan cepat (`Semua Dokumentasi`, `Dokumentasi Umum`, `Operasi SAR`, serta ringkasan `Foto & Video`).
  - Bilah pencarian judul/label, filter jenis media (`Semua / Foto / Video`), filter bulan pembuatan (`YYYY-MM`), dan tombol utama **Buat Dokumentasi**.
  - Pengelompokan kronologis per **Bulan & Tahun** (mis. `November 2026`) dalam bahasa Indonesia beserta penanda waktu pembuatan WITA, lencana `UMUM` atau tautan `OPS #ID`, tombol `Lihat` (lightbox), `Edit`, `Unduh`, dan `Hapus` (khusus `ADMIN`).
- **Studio (`views/operasi/dokumentasi_studio.php` & `assets/js/pages/operasi_dokumentasi.js`):**
  - Saat dibuka melalui `/dokumentasi.php?action=create` atau `?action=edit&id={docId}`, Kartu 1 menampilkan dropdown **Kaitan Operasi SAR (Opsional)** dengan pilihan default `Dokumentasi Umum (Tanpa Operasi SAR)` dan daftar seluruh `Operasi` berstatus `OPEN`.
  - Saat mode Dokumentasi Umum aktif: label `Hari Operasi` berubah menjadi `Label Kategori` (default `KEGIATAN`), judul default `Dokumentasi Kegiatan KPP Kelas A Kendari`, dan pita kicker tema *Editorial Modern* menampilkan `DOKUMENTASI • {LABEL}` (tanpa kata `OPERASI SAR`). Saat Operasi SAR dipilih, label kembali menjadi `Hari Operasi` (`H1`..`HN`) dan judul otomatis menyesuaikan nama operasi jika belum diketik manual.
