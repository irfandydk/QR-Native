# Desain Pemisahan Halaman Operasi SAR & Dashboard Baru (Hybrid Command-Landing)

**Tanggal:** 2026-11-20  
**Status:** Menunggu Persetujuan  
**Lingkup:** QR-SAR (KPP Kelas A Kendari — Basarnas)

---

## 1. Tujuan & Perubahan Arsitektur Navigasi

Memisahkan peran **Dashboard** dan **Operasi SAR** agar aplikasi memiliki halaman beranda informatif bergaya *landing page* yang bersih, relevan secara operasional, serta kompatibel penuh dengan seluruh pengaturan tema di Pengaturan Admin, sekaligus menjaga seluruh fungsi daftar & manajemen operasi tetap utuh.

### Pemetaan Rute & Menu Sidebar

| Urutan Sidebar | Label Menu | URL | Controller → View | Kondisi Aktif (`$currentRoute`) |
|---|---|---|---|---|
| 1 | **Dashboard** | `/index.php` | `DashboardController::index()` → `views/dashboard.php` | `dashboard` |
| 2 | **Operasi SAR** *(+ badge jumlah `OPEN` jika > 0)* | `/operasi.php` | `OperasiController::index()` → `views/operasi/list.php` | `operasi`, `operasi_detail`, `operasi_form` |
| 3 | **Ploting Peta SAR** | `/gis.php` | `GisController::index()` → `views/gis.php` | `gis` |
| 4 | **BRA SAR** | `/bra.php` | `BraController::handle()` → `views/bra/*` | `bra` |
| 5 | **Master Data** | `/master.php` | `MasterController::index()` → `views/master/index.php` | `master` |
| 6 | **Pengaturan Admin** *(khusus Admin)* | `/admin.php` | `AdminController::index()` → `views/admin/index.php` | `admin` |

- **Halaman default pasca-login:** Pengguna tetap mendarat di **Dashboard baru (`/index.php`)**, dengan tombol CTA langsung ke **Operasi SAR (`/operasi.php`)** dan **Ploting Peta GIS (`/gis.php`)**.
- **Halaman Operasi SAR (`/operasi.php`):** Memindahkan 100% isi halaman daftar operasi saat ini (metrik operasi, filter status default `OPEN`, filter bulan/jenis, pencarian, kartu operasi, modal WA, hapus, dan ekspor CSV) dari `views/dashboard.php` ke `views/operasi/list.php`.
- **Badge Sidebar Operasi Aktif:** `views/layout/sidebar.php` menampilkan badge kecil jumlah operasi berstatus `OPEN` (via query skalar ringan `OperasiModel::countOpen()`) hanya ketika jumlahnya `> 0`.

---

## 2. Arsitektur Data Ringkas (Zero Overhead)

1. **`models/OperasiModel.php`:**
   - Tambahkan `public static function countOpen(): int` dengan 1 query ringan `SELECT COUNT(*) FROM Operasi WHERE status = 'OPEN'` untuk badge sidebar.
2. **`models/GisModel.php`:**
   - Tambahkan `public static function getStatsSummary(): array` dengan 1 query agregat skalar (tanpa memuat koordinat/geometri):
     `['points' => int, 'polygons' => int, 'patterns' => int, 'routes' => int, 'total' => int]`.
3. **`controllers/DashboardController.php`:**
   - Mengambil `OperasiModel::getStats()`, `GisModel::getStatsSummary()`, dan `SettingsModel::getSettings()`, lalu meneruskannya ke `views/dashboard.php` dengan `title => 'Dashboard QR-SAR'`, `route => 'dashboard'`, `navbarTitle => 'Dashboard QR-SAR'`.
4. **`controllers/OperasiController.php`:**
   - Default GET action (`operasi.php` tanpa `action`) merender `'operasi/list'` dengan `title => 'Operasi SAR'`, `route => 'operasi'`, `navbarTitle => 'Operasi SAR'`.

---

## 3. Desain UI Dashboard Baru — Opsi A (*Hybrid Command-Landing*)

### 3.1 Kompatibilitas 100% Pengaturan Tema Admin

QR-SAR mendukung 3 sumbu tema secara bersamaan:
1. **Gaya Tampilan (`data-ui`):** `klasik` (`assets/css/style.css`) dan `minimal` (`assets/css/theme-minimal.css` — *Minimal Glass*).
2. **Mode Warna (`data-theme` / `.dark`):** Mode Terang dan Mode Gelap.
3. **Palet Warna Aksen (`data-palette`):** `orange`, `navy`, `emerald`, dan `gold` (aktif di gaya Klasik; di Minimal Glass otomatis memakai aksen oranye lembut `--m-accent`).

**Aturan styling ketat yang diterapkan di `views/dashboard.php`:**
- Seluruh kartu memakai `.bento-card-v1` dan kelas permukaan standar (`bg-white dark:bg-slate-900`, `border-slate-200 dark:border-slate-800`, `text-slate-900 dark:text-white`, `text-slate-500 dark:text-slate-400`) yang sudah dipetakan secara penuh oleh `theme-minimal.css` maupun `style.css`.
- Seluruh aksen primer (tombol CTA utama, lencana nomor langkah, chip sorotan, fokus keyboard `:focus-visible`, hover border) memakai CSS custom properties semantik yang sudah didefinisikan sistem: `var(--primary)`, `var(--primary-hover)`, `var(--primary-light)`, dan `var(--primary-glow)`.
- Tanpa gradien gelap paksaan, tanpa warna neon hardcoded, dan menghormati `@media (prefers-reduced-motion: reduce)`.

### 3.2 Semantik HTML & Aksesibilitas Kartu (`modern-web-guidance: card`)

- Setiap bagian utama menggunakan `<section aria-labelledby="...">` dengan hierarki heading teratur (`h1` → `h2` → `h3`).
- Alur kerja 5 tahap menggunakan daftar terurut `<ol>` berisi `<li><article>`.
- Setiap kartu fitur/alur yang dapat diklik menggunakan pola **stretched-link** (`<article class="dash-card ...">` dengan `<a href="..." class="dash-card-link">` di dalam `<h3>` dan pseudo-elemen `::after { content: ""; position: absolute; inset: 0; }`), sehingga seluruh kartu mudah diklik namun pembaca layar hanya membacakan judul singkat yang jelas serta menampilkan ring fokus bersih pada kartu via `:has(a.dash-card-link:focus-visible)`.
- Seluruh ikon SVG dekoratif diberi `aria-hidden="true"`.

### 3.3 Struktur 5 Blok Konten (Relevan, Ringkas & Sederhana)

1. **Blok 1 — Hero Operasional Ringkas:**
   - Logo resmi satker (`getAppLogoUrl()`) + nama satker dinamis dari Pengaturan Admin (`satker_nama`, `satker_kode`).
   - Sapaan personal petugas login (`Halo, <Nama>` + lencana peran `Administrator` / `Operator Siaga`).
   - Judul utama & deskripsi padat 2 baris mengenai fungsi **QR-SAR (Quick Report SAR Digital)**.
   - Baris aksi cepat:
     - Tombol primer (`.dash-accent-btn`): **Buka Operasi SAR →** (`/operasi.php`)
     - Tombol sekunder: **Ploting Peta GIS** (`/gis.php`)
     - Tombol tersier halus: **+ Operasi Baru** (`/operasi_form.php`)

2. **Blok 2 — 4 Kartu Metrik Live Ringkas (Klik Langsung ke Modul):**
   - **Operasi Aktif (`OPEN`)** → `/operasi.php?status=OPEN` (dengan titik indikator aktif hijau)
   - **Total Operasi SAR** (`Aktif + Selesai`) → `/operasi.php?status=ALL`
   - **Berita Radio SAR (BRA)** → `/bra.php`
   - **Komponen Peta GIS** (total `Titik • Area • Pola • Rute`) → `/gis.php`

3. **Blok 3 — Alur Kerja Terpadu Operasi SAR (5 Tahap End-to-End):**
   - `01` **Laporan Awal & Inisiasi** → `/operasi_form.php` *(Catat kejadian, LKP, POB, unsur & alut; siap salin format WhatsApp)*
   - `02` **Ploting Peta Taktis GIS** → `/gis.php` *(Plot titik SAR, Search Area, Pola IAMSAR PS/CS/E-SAR, rute/ETA, KML/KMZ)*
   - `03` **Rencana Operasi (RENOPS)** → `/operasi.php?status=OPEN` *(Impor SRU dari peta, capture peta 16:9, editor A4 live & QR verifikasi)*
   - `04` **BRA & Laporan Perkembangan** → `/bra.php` *(Auto-draft sandi telegram dinas TWU/TWB, LAPBANG kumulatif, ekspor PDF A4)*
   - `05` **Penutupan & Arsip Terverifikasi** → `/operasi.php?status=CLOSED` *(Kunci arsip operasi CLOSED audit-safe & verifikasi keabsahan via Barcode QR)*

4. **Blok 4 — Fitur Utama Aplikasi (Bento Grid 3×2 Clean):**
   - **Manajemen Operasi SAR** (`/operasi.php`) — Laporan Awal, RENOPS, SITREP/LAPBANG, Penutupan, dan generator pesan WhatsApp.
   - **Ploting Peta Taktis & IAMSAR** (`/gis.php`) — Multi-layer peta, 3 bentuk Search Area, Pola PS/CS/E-SAR otomatis, jarak & ETA, impor/ekspor KML/KMZ, serta proteksi titik Pangkalan/Dermaga.
   - **Editor & Cetak RENOPS SMEAC** (`/operasi.php?status=OPEN`) — Penyuntingan langsung di lembar A4, sisip capture peta 16:9, dokumentasi foto drag-reorder, cetak draf & terbit.
   - **Berita Radio SAR (BRA Digital)** (`/bra.php`) — Format baku telegram militer Basarnas (BRA Operasi SAR, Mapel, Pergerakan Kapal) & unduh PDF vektor A4.
   - **Verifikasi Dokumen QR Kriptografis** (`/bra.php`) — Tanda tangan token digital pada naskah RENOPS & BRA yang dapat dipindai keabsahannya secara publik.
   - **Master Data & Kontrol Sistem** (`/master.php` untuk User, `/admin.php` untuk Admin) — Pengelolaan Unit SAR, Alut, Personel, RBAC Admin/User, serta kustomisasi tema Klasik/Minimal.

5. **Blok 5 — Keunggulan Sistem & Manfaat Operasional (Grid 2 Kolom Seimbang):**
   - **Keunggulan Aplikasi (4 Poin Padat):**
     - *Siap Luar Jaringan & LAN (Offline-First)* — Font, peta cadangan, dan seluruh pustaka lokal berjalan stabil meski koneksi internet terbatas.
     - *Satu Sumber Data Terpadu (Single Source of Truth)* — Koordinat, SRU, pola pencarian, dan aset dari GIS mengalir otomatis ke RENOPS dan BRA.
     - *Standar Operasional Basarnas & IAMSAR* — Perhitungan geodesi maritim (NM/KM/M), sandi telegram TWU/TWB, dan format SMEAC baku.
     - *Keamanan Berlapis & Audit-Safe* — Pemisahan hak akses Admin/User, proteksi titik pangkalan/dermaga, penguncian operasi `CLOSED`, dan token QR.
   - **Manfaat Operasional (4 Poin Padat):**
     - *Mempercepat Penyusunan Laporan* — Mengurangi waktu pembuatan RENOPS, LAPBANG, dan BRA dari puluhan menit menjadi hitungan menit.
     - *Minim Kesalahan Koordinat* — Eliminasi salah ketik DMS/DD antar-dokumen melalui impor langsung dari kanvas peta taktis.
     - *Format Pelaporan Seragam* — Konsistensi struktur dokumen lintas regu siaga, Pos SAR, dan Unit Siaga SAR.
     - *Akuntabilitas & Kesiapan Pimpinan* — Naskah cetak siap verifikasi keaslian via QR dan arsip riwayat operasi terpusat.

---

## 4. Kompatibilitas Pengujian & Verifikasi

- Perbarui referensi jalur berkas pada 2 test lama (`tests/test_dashboard_default_open.php` dan `tests/test_tahap3_frontend_gis_print.php`) agar menguji `views/operasi/list.php` (halaman Operasi SAR yang memindahkan daftar operasi).
- Tambahkan test suite khusus `tests/test_dashboard_landing_dan_operasi_split.php` yang memverifikasi:
  - `OperasiModel::countOpen()` dan `GisModel::getStatsSummary()` mengembalikan nilai akurat dari DB `qrsar_test`.
  - `DashboardController::index()` merender `views/dashboard.php` (`route => 'dashboard'`) dan `OperasiController::index()` merender `views/operasi/list.php` (`route => 'operasi'`).
  - `views/layout/sidebar.php` memiliki 2 menu terpisah (`Dashboard` → `/index.php` dan `Operasi SAR` → `/operasi.php`) dengan badge operasi aktif.
  - `views/dashboard.php` memuat seluruh 5 blok (Hero, 4 metrik live, Alur Kerja 5 tahap, 6 Fitur Utama, Keunggulan & Manfaat), memakai token CSS tema (`var(--primary)` / `.bento-card-v1`), dan merender bersih tanpa error PHP.
- Kompilasi ulang Tailwind CSS (`npx -y tailwindcss@3.4.17 ...`), jalankan seluruh PHP & JS test suite, verifikasi visual langsung di browser lokal pada **Gaya Klasik (Terang & Gelap + Palet)** maupun **Minimal Glass (Terang & Gelap)**, lalu jalankan `graphify update .`.
