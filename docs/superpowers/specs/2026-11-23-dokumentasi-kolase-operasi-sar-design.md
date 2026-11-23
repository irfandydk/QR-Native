# Desain Fitur: Sub-Halaman Dokumentasi Kolase Operasi SAR (Foto & Video) + Referensi Logo ANOA Rescue

**Tanggal:** 2026-11-23  
**Status:** Disetujui Pengguna (Pendekatan A)  
**Cakupan:** QR-SAR Basarnas KPP Kelas A Kendari

## 1. Tujuan & Latar Belakang
Petugas siaga dan humas KPP Kelas A Kendari rutin mempublikasikan kolase dokumentasi Operasi SAR berformat resmi (kop oranye 3 logo: Basarnas, SAR Nasional, ANOA Rescue; alamat & nomor darurat; pill tanggal; judul operasi & hari ke-N; grid foto/video bersudut membulat; footer oranye berisi situs web & akun media sosial). Selama ini kolase disusun secara manual di aplikasi terpisah.

Fitur ini menambahkan:
1. **Referensi Logo Ketiga (ANOA Rescue)** di Pengaturan Aplikasi (`admin.php?tab=satker`, kolom `AppSettings.logo_url_3`) dengan aset bawaan `assets/images/logo_anoa_rescue.png`.
2. **Tab `Dokumentasi` pada Detail Operasi SAR** (`views/operasi/detail.php`) untuk menampilkan arsip hasil kolase foto/video operasi beserta aksi unduh dan hapus.
3. **Sub-Halaman Studio Dokumentasi Kolase** (`/operasi.php?action=dokumentasi&id={id}`) untuk merakit kolase resmi **4 hingga 12 slot** dalam berbagai ukuran kanvas **vertikal, persegi, maupun horizontal**, mendukung **foto dan video**, dengan rendering HTML5 Canvas + `MediaRecorder` langsung di browser serta penyimpanan hasil akhir ke arsip operasi.

## 2. Pendekatan Arsitektur (Pendekatan A — Dipilih)
- **Pendekatan A (Dipilih):** Tab `Dokumentasi` di Detail Operasi sebagai daftar arsip + tombol membuka sub-halaman studio penuh (`/operasi.php?action=dokumentasi&id={id}`), sejalan dengan pola editor RENOPS (`action=renops_dokumen`). Keuntungan: ruang kerja lega untuk kanvas vertikal/horizontal dan 4–12 slot media, halaman Detail Operasi tetap ringan, dan hasil akhir (bukan belasan video mentah) tersimpan aman di server.
- **Pendekatan B (Ditolak):** Menempatkan studio langsung inline di dalam tab Detail Operasi. Trade-off: area kerja sempit di bawah panel header operasi dan membebani pemuatan awal halaman detail.

## 3. Spesifikasi Komponen

### 3.1 Referensi Logo Ketiga (ANOA Rescue)
- Berkas default: `assets/images/logo_anoa_rescue.png` (disalin dari unggahan pengguna).
- Migrasi: `database/migrations/2026_11_23_add_logo_url_3.sql` menambah kolom `logo_url_3 LONGTEXT NULL DEFAULT NULL AFTER logo_url_2` pada `AppSettings`.
- Helper: `getAppLogo3DataUri()` di `config/app.php` memakai `qrsar_logo_data_uri('logo_url_3', ROOT_DIR . '/assets/images/logo_anoa_rescue.png')`.
- Model & Controller: `SettingsModel::updateSettings()` menyertakan `logo_url_3`; `AdminController::index()` (`save_satker`) memproses `$this->prosesLogoSatker('logo_file_3', 'logo_url_3', $data)`.
- UI: `views/admin/partials/tab_satker.php` menampilkan grid 3 logo sejajar (Logo Utama Kantor SAR, Logo Kedua Kop Dokumen, Logo Ketiga ANOA Rescue / Dokumentasi).

### 3.2 Skema Database, Model & API Arsip Dokumentasi
- Tabel `OperasiDokumentasi` (`database/migrations/2026_11_23_create_operasi_dokumentasi.sql`):
  - Kolom: `id_dokumentasi`, `id_operasi` (FK `ON DELETE CASCADE`), `judul`, `hari_ke`, `tanggal_label`, `media_type` (`image`|`video`), `mime_type`, `aspect_ratio`, `orientation` (`vertical`|`horizontal`|`square`), `slot_count` (4–12), `layout_preset`, `width_px`, `height_px`, `file_path`, `thumb_path`, `file_size`, `created_by`, `created_at`.
- Terdaftar di `BackupService::MANAGED_TABLES` dan dibersihkan fisiknya oleh `OperasiModel::delete()`.
- `OperasiDokumentasiModel`:
  - Direktori `uploads/operasi_dokumentasi/` dengan guard `index.php` (403).
  - Validasi ketat `finfo` MIME: gambar (`image/jpeg`, `image/png`, `image/webp`, maks 12 MB, <= 40 MP) dan video (`video/webm`, `video/mp4`, cek magic bytes header, maks 20 MB sesuai `.user.ini`), plus validasi `slot_count` antara 4 dan 12 serta `thumb_file` opsional untuk poster video.
- Endpoint API (`api/controllers/OperasiDokumentasiApiController.php`, CSRF + Auth):
  - `GET /api/operasi/{id}/dokumentasi`
  - `POST /api/operasi/{id}/dokumentasi` (`multipart/form-data`)
  - `DELETE /api/operasi/{id}/dokumentasi/{docId}`

### 3.3 Engine Layout & Studio Kolase (Foto & Video)
- Modul murni `assets/js/dokumentasi/collage_layouts.js`:
  - Preset ukuran kanvas:
    - Vertikal: `2:3` (`1080x1620`, default standar contoh), `4:5` (`1080x1350`), `9:16` (`1080x1920`), `A4` (`1240x1754`).
    - Persegi: `1:1` (`1080x1080`).
    - Horizontal: `16:9` (`1920x1080`), `3:2` (`1620x1080`), `4:3` (`1440x1080`).
  - Preset pola grid untuk setiap jumlah **4–12 slot** (termasuk grid simetris & hero/asimetris tanpa celah kosong) dengan fungsi deterministik `computeSlotRects()`, `computeCoverCrop()`, `wrapCanvasLines()`, dan `formatCollageFilename()`.
- Studio controller `assets/js/pages/operasi_dokumentasi.js`:
  - Merender bingkai resmi KPP Kelas A Kendari persis contoh: kop oranye (`#F96302`) 3 logo + teks identitas/call center kanan, garis pembatas + pill tanggal oranye-cokelat (`#A83E00`) kiri, judul tebal rata tengah di bidang putih, grid 4–12 foto/video sudut membulat + bayangan halus, footer oranye dengan pill website kanan atas & 3 ikon medsos (Instagram, Facebook, X) di bawah.
  - Unggah foto (`image/*`) dan video (`video/*`) secara satuan atau massal, tukar urutan slot, zoom & geser posisi crop per slot, scrubber frame video, pratinjau pemutaran video langsung di kanvas, ekspor gambar (JPG/PNG), ekspor video (`canvas.captureStream` + `MediaRecorder` + durasi & audio opsional), dan simpan langsung ke arsip operasi.

## 4. Batasan Global
- Tanpa emoji di kode, komentar, maupun teks UI.
- Tanpa `ffmpeg` atau dependensi NPM eksternal tambahan (100% browser native Canvas + MediaRecorder + PHP GD/finfo bawaan).
- Seluruh unggahan melewati batas kepercayaan `finfo`, pembatasan ukuran, dan proteksi CSRF + Auth.
- Tidak boleh memutus satu pun dari 97 suite tes PHP dan 7 suite tes JS yang sudah ada.

## 5. Addendum Penyesuaian Tahap 2 (Disetujui 2026-11-23)
1. **Kontrol Langsung di Pratinjau (`#collageInteractiveOverlay`)**: setiap slot foto/video dapat langsung diklik untuk unggah, diseret di dalam slot untuk atur posisi X/Y, di-zoom (`-`/`+` atau scroll roda mouse, default fit-to-slot), diseret lewat handle untuk reorder/tukar posisi, dihapus 1 per 1, maupun dipilih banyak untuk **Bulk Delete** (`Hapus Terpilih` / `Hapus Semua`) — tanpa membocorkan elemen UI ke hasil ekspor.
2. **Kartu Media Kiri Ringkas**: menghapus daftar kartu `#1..#12` di panel kiri; hanya menampilkan dropzone unggah massal, ringkasan slot terisi, dan inspector halus untuk slot aktif.
3. **Nomor WhatsApp Satker (`AppSettings.no_wa`)**: mengganti isian *Frekuensi Komunikasi SAR Default* di Pengaturan Satker menjadi **Nomor WhatsApp Kantor / Siaga (WA)**; otomatis memformat kop kolase menjadi `EMERGENCY CALL : 115 / {telp} / WA: {no_wa}` bila terisi.
4. **Gaya Kedua (`Editorial Modern`)**: pemilih gaya antara **Klasik Resmi KPP** dan **Editorial Modern** (kop putih bersih aksen oranye, judul editorial rata kiri, grid foto rapi, footer deep navy-slate `#0F172A`).
5. **Kualitas Ekspor Tinggi (Full HD → 2K QHD → 4K Ultra HD / Original)**: rendering offscreen beresolusi penuh langsung dari sumber foto/video asli dengan `imageSmoothingQuality = 'high'` (`1x` FHD 1080p, `1.5x` 2K QHD, `2x` 4K UHD hingga `3840x2160` / `2160x3240`, bitrate video hingga `16 Mbps`).

