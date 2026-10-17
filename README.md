# QR-SAR (Quick Report Search and Rescue) - PHP Native & MySQL

## Sistem Pelaporan Operasi SAR & Berita Radio SAR (FILE AKTIF)

Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari — Badan Nasional Pencarian dan Pertolongan (BASARNAS).

---

## 🌟 Ringkasan Aplikasi

Aplikasi **QR-SAR PHP Native** ini adalah implementasi sistematis, elegan, dan profesional berstandar kedinasan Basarnas yang dibangun menggunakan **PHP Native murni (PHP 8.0+)** dengan basis data **MySQL**.

Aplikasi ini mereplikasi 100% workflow, struktur data, format laporan dinas Basarnas, dan estetika visual dari repositori referensi [QR-SAR](https://github.com/irfandydk/QR-SAR.git), dilengkapi fitur:

1. **Inisiasi Laporan Awal Operasi SAR** (Format FILE AKTIF resmi Basarnas dengan DMS GPS converter, Haversine NM/Km, perhitungan heading/azimuth dari Kansar).
2. **Siklus Rencana Operasi SAR (RENOPS H.X)** (Pembagian Sektor A-B-C-D, SRU 1 & SRU 2, alut terlibat, dan pola pencarian).
3. **Laporan Perkembangan Harian (SITREP / LAPBANG)** (Pembaruan status korban: Selamat / Meninggal Dunia / Dalam Pencarian / Hilang, kendala operasi, cuaca).
4. **Penutupan Operasi & Rollback** (Debriefing penutupan resmi & opsi pembukaan kembali bila ada perkembangan korban).
5. **Naskah Berita Radio SAR (BRA SAR)** (Format resmi dinas Senkom: Panggilan, Jenis, Derajat `SGR`, DARI, AKSI, INFO, TWU, TWB, Seksi AAA–ZZZ dengan word counter otomatis, dan footer 4 kolom Senkom).
6. **Pengesahan Barcode QR Digital Terenkripsi** (QR Code Canvas resolusi tinggi dengan Error Correction level H dan logo Basarnas di tengah kode QR, diverifikasi ke link resmi dokumen).
7. **Tactical Maritime GIS & Map Plotting** (Peta interaktif Leaflet dengan citra Satelit Resolusi Tinggi Esri World Imagery & OpenStreetMap, radar pulsing LKP, kalkulator rute berkecepatan knot alut, kalkulator pola pencarian IAMSAR: Parallel Sweep, Creeping Line, Expanding Square, Sector Search, serta ekspor GeoJSON & KML).
8. **Master Data & Pengaturan Terpusat** (Armada Alut laut/darat/palsar, unsur potensi gabungan TNI/Polri/Masyarakat, daftar Pejabat SMC, profil Satker `SAR-304`, backup JSON, dan ekspor SQL dump mandiri).
9. **Desain Visual Eksklusif Basarnas** (Warna resmi Basarnas Oranye `#F97316`, dark mode/light mode dinamis, layout responsif glassmorphism, dan format cetak lembar fisik telegram A4/F4/Folio/Letter/Legal 80%-125%).

---

## 📁 Struktur Direktori Proyek

```text
QR-SAR/
├── public/                  # DocumentRoot produksi resmi (Front Controller)
│   ├── .htaccess            # Apache hardening & rewrite rule ke index.php
│   ├── index.php            # Entrypoint utama sistem publik (bootstrap & dispatch)
│   ├── router.php           # Local CLI dev router (php -S 127.0.0.1:8000 -t public public/router.php)
│   └── assets -> ../assets  # Symlink aset publik untuk performa statis cepat
├── config/                  # Konfigurasi aplikasi & database
│   ├── app.php              # Bootstrap inti, session, CSRF guard, autoloader, zona waktu WITA
│   └── database.php         # PDO Database Singleton connection & auto-reconnect
├── helpers/                 # Utility & business logic helpers
│   ├── AuthHelper.php       # Role guard (ADMIN/USER), session management & perizinan aksi
│   ├── ViewHelper.php       # Centralized View Helper (renderView & domain view resolution)
│   ├── Router.php           # Central router, clean URLs dispatcher, asset server & security barrier
│   ├── TemplateHelper.php   # Generator naskah WhatsApp resmi FILE AKTIF & kalkulator hari SAR
│   ├── BraHelper.php        # Nomor urut atomik telegram ([urut]/SAR-304/[MMYY]), kalkulasi kata
│   ├── GeoHelper.php        # DMS <-> Decimal, Haversine NM/Km, Azimuth/Bearing, Waypoint math
│   └── AiHelper.php         # Integrasi asisten AI Gemini & generator narasi resmi
├── models/                  # Domain Data Layer (Prepared Statements PDO)
│   ├── OperasiModel.php     # CRUD Operasi, penutupan operasi, rollback, statistik KPI
│   ├── RenopsModel.php      # Rencana Operasi harian (H.1, H.2, dst.) & pembagian sektor SRU
│   ├── LaporanModel.php     # SITREP harian, status korban (S/MD/DP/H), timeline lapangan
│   ├── BraModel.php         # Dokumen naskah telegram radio, nomor atomik & validasi token QR
│   ├── SarUnitModel.php     # Domain model Pos SAR, Unit Siaga, & KN SAR Kendari
│   ├── PejabatModel.php     # Domain model Pejabat penandatangan, Kansar & Kepala Seksi
│   ├── MasterModel.php      # Facade model backward-compatible untuk armada, tim, & alut
│   ├── GisModel.php         # Data titik taktis, poligon pencarian, rute & pola IAMSAR
│   ├── UserModel.php        # Manajemen akun, otentikasi login, hashing password & audit
│   └── SettingsModel.php    # Profil Satker SAR-304, backup JSON & ekspor SQL
├── views/                   # Presentation Layer (Template Berbasis Domain)
│   ├── operasi/             # Modul Operasi SAR
│   │   ├── form.php         # Form inisiasi laporan awal operasi & kalkulator koordinat
│   │   ├── detail.php       # Tabbed Hub (Ringkasan, Renops, Sitrep, BRA, GIS, Ekspor)
│   │   ├── renops_form.php  # Form Rencana Operasi SAR & pembagian sektor SRU
│   │   └── sitrep_form.php  # Form Laporan Perkembangan harian SITREP/LAPBANG
│   ├── bra/                 # Modul Berita Radio SAR
│   │   ├── form.php         # Form builder telegram Senkom & word counter dinamis
│   │   ├── list.php         # Arsip dokumen BRA (MAPEL, SAR UMUM, PERGERAKAN KAPAL)
│   │   ├── view.php         # Lembar cetak fisik telegram Basarnas & verifikasi QR
│   │   └── verify_invalid.php # Tampilan respon dokumen manipulatif / token tidak sah
│   ├── master/              # Modul Master Data
│   │   ├── index.php        # Manajemen armada kapal, alut darat, palsar & potensi SAR
│   │   └── settings.php     # Pengaturan instansi, profil Satker, backup & restore
│   ├── admin/               # Modul Administrasi
│   │   └── index.php        # Manajemen hak akses, user accounts, & log aktivitas
│   ├── auth/                # Modul Otentikasi
│   │   └── login.php        # Portal login otentikasi petugas SAR
│   ├── layout/              # Template Tata Letak Bersama
│   │   ├── header.php       # HTML5 head, meta security, conditional CSS loader
│   │   ├── sidebar.php      # Navigasi utama sidebar responsif dengan badge satker
│   │   ├── navbar.php       # Header atas, dark mode switch, info user aktif
│   │   ├── footer.php       # Footer kedinasan, conditional JS loader, modal dialogs
│   │   ├── public_header.php # Header khusus portal verifikasi publik mandiri
│   │   └── public_footer.php # Footer portal publik
│   ├── errors/              # Halaman Error HTTP Terstandarisasi
│   │   ├── 403.php          # Akses Ditolak
│   │   ├── 404.php          # Berkas / Rute Tidak Ditemukan
│   │   └── 500.php          # Kesalahan Internal Server
│   ├── dashboard.php        # Beranda ringkasan operasi aktif & KPI real-time
│   └── gis.php              # Antarmuka GIS maritim taktis & pola pencarian IAMSAR
├── api/                     # RESTful JSON API Backend
│   ├── controllers/         # Controller API Modular Berbasis Domain
│   │   ├── BaseApiController.php     # Abstraksi respon JSON, error handling, session guard
│   │   ├── AuthApiController.php     # Endpoint otentikasi & session checking
│   │   ├── OperasiApiController.php  # Endpoint data operasi & kalkulasi jarak/heading
│   │   ├── RenopsApiController.php   # Endpoint dokumen renops & pembagian sru
│   │   ├── SitrepApiController.php   # Endpoint laporan perkembangan & status korban
│   │   ├── BraApiController.php      # Endpoint telegram radio, preview & nomor atomik
│   │   ├── MasterApiController.php   # Endpoint alut, tim potensi, pangkalan SAR & pejabat
│   │   ├── SettingsApiController.php # Endpoint konfigurasi & profil satker
│   │   ├── AiApiController.php       # Endpoint integrasi asisten AI Gemini
│   │   └── GisApiController.php      # Endpoint sinkronisasi bundle peta taktis
│   └── index.php            # API Front Controller & request dispatcher
├── assets/                  # Static Web Assets (CSS, JS, Fonts, Images)
│   ├── vendor/              # Third-Party Libraries Terisolasi
│   │   ├── leaflet/         # Leaflet CSS, JS, dan marker assets
│   │   ├── sweetalert2/     # SweetAlert2 alert engine & CSS
│   │   ├── qrcode/          # QRCode.js engine
│   │   ├── tailwind/        # Tailwind CSS offline runtime
│   │   └── jszip/           # JSZip file compressor
│   ├── js/                  # Application JavaScript
│   │   ├── pages/           # Extracted Browser-Cacheable Page Scripts
│   │   │   ├── renops_form.js    # Logic pembagian SRU & kalkulator sektor
│   │   │   ├── operasi_form.js   # Logic inisiasi operasi & kalkulator koordinat
│   │   │   ├── bra_form.js       # Logic format Senkom, word count & barcode
│   │   │   ├── operasi_detail.js # Tab switching, rollback modal & detail GIS
│   │   │   └── sitrep_form.js    # Logic input sitrep & kalkulasi korban
│   │   ├── app.js           # Theme switcher, CSRF header hook, modal system
│   │   ├── bra.js           # Telegram section handler & word counter
│   │   ├── gis.js           # TacticalGisMap class & rendering engine Leaflet
│   │   └── gis_master.js    # Full GIS workstation mapping interface
│   ├── css/
│   │   └── style.css        # Basarnas Design System, Glassmorphism, Print CSS
│   └── images/              # Assets gambar resmi & icon instansi
├── database/                # Skrip Migrasi & Basis Data
│   ├── migrate.php          # CLI Database Migration Runner (Zero runtime locking)
│   ├── db_sar_qr.sql        # Standalone dump MySQL lengkap DDL + data siap pakai
│   └── seed.sql             # SQL data awal pejabat, user, dan pangkalan SAR
├── storage/                 # Penyimpanan Internal Terproteksi (.htaccess blocked)
│   ├── logs/                # Berkas log aplikasi & audit trail
│   ├── backups/             # Berkas cadangan dump SQL & JSON
│   └── cache/               # Cache sementara sistem
└── tests/                   # Automated Regression Test Suites (CLI Only)
    ├── test_phase2_architecture.php    # Uji verifikasi arsitektur backend & database
    ├── test_phase3_frontend_assets.php  # Uji verifikasi aset vendor & ekstraksi JS
    └── test_phase4_standardization.php  # Uji verifikasi webroot, routing & modular view
```

---

## 🚀 Panduan Menjalankan Aplikasi

### 1. Prasyarat Sistem

- **PHP** versi 8.0, 8.1, 8.2, 8.3, atau 8.5+ (Ekstensi `pdo_mysql`, `json`, `mbstring` aktif).
- **MySQL / MariaDB** (Versi 5.7, 8.0, atau MariaDB 10.4+).
- Web Server: Built-in PHP Server, Apache (XAMPP/Laragon/MAMP), atau Nginx.

### 2. Import Basis Data MySQL

Buat database bernama `qrsar` (atau sesuaikan pada konfigurasi), lalu import file SQL:

```bash
# Melalui terminal MySQL:
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS qrsar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p qrsar < database/db_sar_qr.sql
```

*Atau gunakan **phpMyAdmin**: Buat database `qrsar` baru dan import file `database/db_sar_qr.sql`.*

### 3. Konfigurasi Lingkungan (`.env`)

Salin file `.env.example` menjadi `.env` jika diperlukan (secara default aplikasi langsung mendeteksi koneksi lokal `localhost` dengan user `root` tanpa password):

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qrsar
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Menjalankan Server Lokal & Pilihan Arsitektur

Aplikasi QR-SAR mendukung arsitektur **Dual-Mode Deployment** yang fleksibel:

#### Opsi 1: Mode Cepat Pangkalan (Root Document Root)

Sangat cocok untuk pengujian cepat di laptop siaga menggunakan PHP Built-in Server:

```bash
php -S 127.0.0.1:8000 router.php
```

#### Opsi 2: Mode Modern Aman Produksi (Public Document Root)

Direkomendasikan untuk server produksi Linux (Apache / Nginx). Direktori privat (`config`, `models`, `helpers`, `views`, `database`) berada 100% di luar Document Root:

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

#### Manajemen & Sinkronisasi Aset Publik (Single Source of Truth)

Seluruh aset desain dan berkas unggahan berpusat pada folder `/assets` dan `/uploads`. Folder `/public` secara cerdas terhubung melalui tautan simbolik (*symlink*) otomatis dengan sistem *fallback streaming* PHP jika server tidak mendukung symlink. Gunakan alat bantu CLI untuk mengelola keterhubungan aset:

```bash
# Periksa status keterhubungan aset publik:
php cli/sync_assets.php --status

# Pasang / perbarui tautan simbolik relatif (Linux/macOS):
php cli/sync_assets.php --symlink

# Sinkronisasi salinan fisik penuh (Windows / Hosting tanpa dukungan symlink):
php cli/sync_assets.php --mirror
```

Buka peramban (*browser*) dan akses alamat:
👉 **`http://localhost:8000`**

---

## 🔐 Akun Masuk Sistem (Default Login)

Sistem dilengkapi dua tingkatan peran (*role*):

1. **Administrator Utama (SMC)**
   - **Username**: `admin`
   - **Password**: `Kendari304` *(atau `admin123`)*
   - **Hak Akses**: Hak penuh mengelola Operasi, menerbitkan naskah BRA, menghapus arsip, konfigurasi master alut/tim/pejabat, profil satker, dan backup database.

2. **Operator Siaga SAR & Senkom**
   - **Username**: `user`
   - **Password**: `Kendari304` *(atau `user123`)*
   - **Hak Akses**: Input Laporan Awal, Renops, Sitrep harian, drafting BRA SAR, dan plotting peta taktikal GIS.

---

## 📑 Spesifikasi Fitur Utama

### 1. Inisiasi Laporan Awal (Emergency Quick Report)

- Format baku pesan darurat siaga SAR tanpa nomor surat pada fase respons awal (sesuai standar pesan cepat Basarnas).
- Konversi koordinat instan antara Derajat Menit Detik (DMS) dan Angka Desimal.
- Kalkulasi otomatis jarak Nautical Miles (NM) dan radial derajat kompas dari pangkalan/pos tim yang diberangkatkan ke titik LKP beserta perkiraan waktu tempuh (*Estimated Time of Arrival / ETA*).

### 2. Tabbed Hub Operasi SAR

- **Tab Ringkasan**: Laporan Awal lengkap, visualisasi status korban (*Selamat, Meninggal Dunia, Dalam Pencarian, Hilang*), tombol salin naskah WhatsApp resmi 1-klik, dan cetak dokumen.
- **Tab Rencana Operasi (RENOPS)**: Pembuatan Renops H.1, H.2, dst., dengan grid pembagian SRU 1 & SRU 2, batas koordinat area pencarian, alut, dan frekuensi komunikasi.
- **Tab Laporan Perkembangan (SITREP)**: Entri harian hasil pencarian, cuaca BMKG maritim, faktor penghambat, dan pembaruan korban.
- **Tab BRA SAR Terkait**: Akses cepat ke naskah telegram radio yang tertaut dengan operasi.
- **Tab Tactical Map**: Plotting koordinat LKP dengan radar pulse animasi Leaflet.
- **Tutup & Rollback Operasi**: Fitur penutupan resmi operasi dan pengembalian (*rollback*) status bila ada perkembangan korban baru.

### 3. Berita Radio SAR (BRA) Standar Senkom

- Kategori dokumen: `BRA MAPEL` (Radio Siaran Informasi), `BRA SAR` (Operasi SAR), dan `BRA PERGERAKAN KAPAL` (Pelayaran KN SAR / Rescue Boat).
- Penomoran atomik: `[NOMOR_URUT]/SAR-304/[BULAN][TAHUN]`.
- Seksi dinas alfabetis `AAA`, `BBB`, `CCC`, dst., dengan penghitung kata (*live word counter*) otomatis.
- Format cetak persis dokumen fisik resmi:
  - Header kop logo & instansi
  - Label vertikal kiri: `RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM`
  - Tabel 5 kolom Senkom (Panggilan, Jenis, Nr. Stn, Derajat, Instruksi penerusan)
  - Bagian DARI, AKSI, INFO, TWU, JUMLAH KATA, NO, MENBRA, PERIHAL
  - Tanda tangan pengirim (SMC) disahkan dengan **Barcode QR Digital Terenkripsi (QR Code with Basarnas Logo Center Emblem)**
  - 4 Kolom buku komunikasi operator Senkom (T.W.T, T.W.K, SISTEM, PARAF).

### 4. Tactical Maritime GIS

- Pemetaan maritim interaktif berbasis Leaflet dengan dua pilihan layer: Citra Satelit Resolusi Tinggi (Esri World Imagery) dan Peta Vektor OSM.
- Plotting titik taktis: LKP Kejadian, Kantor SAR, Pos SAR Baubau, Dermaga Sandar, Helipad.
- Perhitungan luas poligon pencarian (*Search Area*) dalam Nautical Miles persegi (NM²).
- Generator pola pencarian standar maritim **IAMSAR (International Aeronautical and Maritime Search and Rescue)**:
  - Parallel Sweep Search (PS)
  - Creeping Line Search (CS)
  - Expanding Square Search (SS)
  - Sector Search (VS)
- Pengukur jarak pelayaran multi-titik (*distance & azimuth bearing*) dengan estimasi durasi tempuh berdasarkan kecepatan knot kapal Basarnas (KN SAR Pacitan 18 knot, RB 210 20 knot, RIB 25 knot, Rubber Boat 10 knot).
- Ekspor data spasial ke format **GeoJSON** dan **KML (Google Earth)**.

---

## 🛡️ Keamanan & Standar Koding

- **PHP 8 Strict Typing** (`declare(strict_types=1);`).
- **CSRF Token Protection** di semua form mutasi data.
- **PDO Prepared Statements** untuk mencegah serangan SQL Injection secara menyeluruh.
- **XSS Prevention** melalui escaping output (`htmlspecialchars`).
- **Role-Based Access Control (RBAC)** untuk melindungi aksi penghapusan dan perubahan data vital.

---

**Basarnas KPP Kendari &bull; AVIGNAM JAGAT SAMAGRAM &bull; Semoga Selamatlah Alam Semesta**
