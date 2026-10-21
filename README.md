# QR-SAR (Quick Report Search and Rescue) - PHP Native & MySQL

## Sistem Pelaporan Operasi SAR, Berita Radio SAR & Verifikasi Dokumen Digital (FILE AKTIF)

Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari — Badan Nasional Pencarian dan Pertolongan (BASARNAS).

---

## 🌟 Ringkasan Aplikasi

Aplikasi **QR-SAR PHP Native** adalah sistem manajemen pelaporan operasi pencarian dan pertolongan (Search and Rescue) berstandar kedinasan Basarnas yang dibangun menggunakan **PHP Native murni (PHP 8.0+)** dengan basis data **MySQL / MariaDB**. 

Sistem ini mengadopsi arsitektur **Modular MVC (Model-View-Controller) dengan Service Layer terpisah**, mereplikasi 100% alur kerja resmi FILE AKTIF Basarnas, naskah Berita Radio SAR (BRA), pemetaan taktikal maritim GIS, serta pengesahan dokumen digital:

1. **Inisiasi Laporan Awal Operasi SAR**: Format pesan darurat baku respons cepat Basarnas, dilengkapi konverter koordinat dua arah DMS $\leftrightarrow$ Decimal GPS, kalkulasi jarak Haversine (Nautical Miles & Kilometer), serta radial azimuth kompas dari pangkalan/pos tim siaga.
2. **Siklus Rencana Operasi SAR (RENOPS H.X)**: Perencanaan harian pembagian Search and Rescue Unit (SRU 1 & SRU 2), penentuan batas koordinat area pencarian, alokasi alut laut/darat/udara, dan frekuensi komunikasi radio taktis.
3. **Laporan Perkembangan Harian (SITREP / LAPBANG)**: Pemantauan harian hasil pencarian, pembaruan status korban (*Selamat / Meninggal Dunia / Dalam Pencarian / Hilang*), kondisi cuaca maritim BMKG, dan faktor penghambat di lapangan.
4. **Penutupan Operasi & Rollback Dinamis**: Debriefing resmi penutupan operasi, pembuatan Berita Acara Penutupan, dan opsi pemulihan status (*rollback*) jika ada laporan korban lanjutan.
5. **Naskah Berita Radio SAR (BRA Standar Senkom)**: Kategori `BRA MAPEL`, `BRA SAR`, dan `BRA PERGERAKAN KAPAL` dengan format resmi dinas Senkom: Panggilan, Jenis, Derajat `SGR`, DARI, AKSI, INFO, TWU, TWB, Seksi dinas alfabetis `AAA–ZZZ`, *live word counter*, dan tabel 4 kolom komunikasi Senkom.
6. **Verifikasi Keabsahan Dokumen Digital (HMAC QR Code)**: Seluruh dokumen resmi (Laporan Awal, RENOPS, SITREP, Berita Acara Penutupan, dan BRA SAR) dilengkapi Barcode QR beresolusi tinggi dengan emblem logo Basarnas di tengah dan token kriptografis HMAC SHA-256 yang terverifikasi ke portal publik mandiri (*Zero-Login Public Verification*).
7. **Studio Capture Peta 16:9 & Map Snapshot**: Fitur tangkapan layar peta taktikal rasio 16:9 terintegrasi pada GIS yang secara otomatis mendeteksi komponen spasial di dalam bingkai (titik LKP, lokasi korban, poligon pencarian, rute alut) dan menyematkannya langsung ke dalam dokumen cetak resmi berserta tabel keterangan komponen.
8. **Tactical Maritime GIS & Kalkulator Pola IAMSAR**: Peta interaktif Leaflet dengan citra Satelit Resolusi Tinggi (Esri World Imagery) dan Peta Vektor OSM, radar pulsing LKP, pengukur rute berkecepatan knot alut Basarnas, kalkulator pola pencarian maritim IAMSAR (*Parallel Sweep, Creeping Line, Expanding Square, Sector Search*), serta ekspor data spasial GeoJSON & KML.
9. **Official PDF Exporter (Anti-Cut Smart Slicing)**: Konversi lembar naskah dinas A4 Portrait menjadi dokumen elektronik PDF yang tajam, presisi, dan bebas huruf terpotong melalui algoritma deteksi spasi putih antar-baris teks (*safe page-cut detection*).
10. **Master Data & Pengaturan Terpusat**: Manajemen 9 Pangkalan Resmi Basarnas Sultra (Kansar Kendari, Pos SAR Kolaka, Baubau, Wakatobi, Konawe Utara, Unit Siaga Muna, Konawe, Kolaka Utara, Dermaga Sandar Bungkutoko), armada Alut, potensi SAR gabungan, data Pejabat SMC, profil Satker `SAR-304`, backup JSON/SQL, dan audit trail log pengguna.

---

## 📁 Struktur Direktori Proyek

```text
QR-SAR/
├── public/                  # DocumentRoot produksi resmi (Front Controller Pattern)
│   ├── .htaccess            # Apache security hardening & URL rewrite dispatcher
│   ├── index.php            # Entrypoint utama sistem publik (bootstrap & Router::dispatch)
│   ├── router.php           # Local CLI dev router (php -S 127.0.0.1:8000 -t public public/router.php)
│   └── assets -> ../assets  # Tautan simbolik ke aset publik untuk performa statis cepat
├── controllers/             # Controller Layer (Pemisahan Logika Alur Bisnis)
│   ├── BaseController.php       # Controller dasar, session guard, CSRF validation, response JSON/view
│   ├── OperasiController.php    # Manajemen Operasi SAR, Renops, Sitrep, Penutupan & cetak dokumen
│   ├── BraController.php        # Dokumen Berita Radio SAR, nomor atomik Senkom & cetak telegram
│   ├── DashboardController.php  # Agregasi data KPI operasi aktif/selesai & statistik real-time
│   ├── GisController.php        # Endpoint halaman GIS maritim taktis & workstation pemetaan
│   ├── MasterController.php     # Pengelolaan alut, unsur potensi SAR gabungan, dan pejabat SMC
│   ├── AdminController.php      # Konfigurasi Satker SAR-304, akun user, backup data & audit log
│   └── AuthController.php       # Autentikasi sesi petugas, login/logout, dan pengamanan akses
├── services/                # Service Layer (Domain Business Logic Terisolasi)
│   ├── SarCalculationService.php   # Perhitungan koordinat DMS/Decimal, Haversine NM/Km, Azimuth, ETA kapal
│   ├── DocumentNumberService.php   # Penomoran atomik dokumen resmi telegram dan surat kedinasan
│   ├── WhatsAppTemplateService.php # Generator naskah pesan WhatsApp resmi FILE AKTIF 1-klik
│   ├── OperasiRelationService.php  # Integritas relasi data operasi, SRU, alut, dan korban
│   ├── BackupService.php           # Mekanisme backup mandiri (ekspor SQL dump & snapshot JSON)
│   └── LogService.php              # Pencatatan riwayat audit trail aktivitas pengguna
├── helpers/                 # Utility & Engine Helpers
│   ├── DocVerifyHelper.php   # Token kriptografis HMAC, URL verifikasi publik dokumen resmi SAR
│   ├── AuditHelper.php       # Audit logging aktivitas petugas, operasi, dan administrasi
│   ├── HierarchyHelper.php   # Perhitungan hari operasi dinamis (H.1, H.2, dst.) & timeline urut tanggal
│   ├── AuthHelper.php        # Role guard (ADMIN / USER), session lifecycle & perizinan aksi
│   ├── ViewHelper.php        # Template compiler & perender partials modular
│   ├── Router.php            # Central router, clean URLs dispatcher, asset server & security barrier
│   ├── TemplateHelper.php    # Format tanggal/waktu resmi Indonesia & WITA, format kedinasan
│   ├── BraHelper.php         # Format telegram Senkom, nomor atomik [urut]/SAR-304/[MMYY], word counter
│   ├── GeoHelper.php         # DMS <-> Decimal, Haversine formula, Azimuth/Bearing, Waypoint math
│   └── AiHelper.php          # Integrasi asisten AI Gemini untuk penulisan narasi laporan dinas
├── models/                  # Domain Data Layer (PDO Prepared Statements)
│   ├── OperasiModel.php              # CRUD Operasi SAR, penutupan, rollback, KPI statistik
│   ├── RenopsModel.php               # Rencana Operasi harian (H.1, H.2, dst.) & pembagian sektor SRU
│   ├── LaporanModel.php              # SITREP harian, status korban (S/MD/DP/H), timeline lapangan
│   ├── BraModel.php                  # Dokumen telegram radio, nomor atomik & validasi token QR
│   ├── DokumenPetaSnapshotModel.php  # Model snapshot peta taktis 16:9 untuk dokumen cetak resmi
│   ├── GisModel.php                  # Titik taktis, poligon pencarian, rute berkecepatan knot & pola IAMSAR
│   ├── SarUnitModel.php              # Data 9 Pangkalan resmi Basarnas Sultra (Kansar, Pos, Unit Siaga)
│   ├── PejabatModel.php              # Pejabat penandatangan, Kansar & Kepala Seksi Operasi
│   ├── MasterModel.php               # Model armada kapal, alut darat, palsar, dan potensi SAR
│   ├── UserModel.php                 # Akun petugas, verifikasi password bcrypt, audit RBAC
│   └── SettingsModel.php             # Profil Satker SAR-304, konfigurasi, backup JSON/SQL
├── views/                   # Presentation Layer (Template Berbasis Domain & Partials)
│   ├── operasi/             # Modul Operasi SAR
│   │   ├── form.php                  # Form inisiasi laporan awal operasi & kalkulator koordinat
│   │   ├── detail.php                # Tabbed Hub Utama Operasi SAR
│   │   ├── renops_form.php           # Form pembuatan Rencana Operasi SAR & pembagian SRU
│   │   ├── sitrep_form.php           # Form Laporan Perkembangan harian SITREP/LAPBANG
│   │   ├── laporan_awal_print.php    # Dokumen cetak resmi fisik & PDF Laporan Awal (+ Peta 16:9 & QR)
│   │   ├── renops_print.php          # Dokumen cetak resmi fisik & PDF RENOPS H.X (+ Peta 16:9 & QR)
│   │   ├── sitrep_print.php          # Dokumen cetak resmi fisik & PDF SITREP H.X (+ Peta 16:9 & QR)
│   │   ├── penutupan_print.php       # Berita Acara Penutupan Operasi (+ Peta 16:9 & QR)
│   │   ├── verify_public.php         # Portal verifikasi keabsahan dokumen publik mandiri
│   │   └── partials/                 # Komponen Tab Modular Operasi
│   │       ├── tab_hierarchy.php         # Timeline hierarkis runtut waktu (Laporan Awal, Renops, Sitrep)
│   │       ├── tab_renops.php            # Arsip dan ringkasan RENOPS harian
│   │       ├── tab_sitrep.php            # Arsip dan ringkasan SITREP harian
│   │       ├── tab_korban.php            # Matriks pembaruan status korban & visualisasi KPI
│   │       ├── tab_kekuatan.php          # Kekuatan personel, alut, dan unsur SAR gabungan
│   │       ├── tab_gis.php               # Tampilan peta taktis terintegrasi per operasi
│   │       ├── tab_bra.php               # Naskah telegram radio yang tertaut dengan operasi
│   │       ├── tab_cetak.php             # Pusat komando cetak PDF & manajemen snapshot peta 16:9
│   │       ├── print_map_snapshot_block.php # Komponen render visual peta 16:9 & tabel komponen
│   │       └── modals.php                # Dialog modal penutupan, rollback, dan hapus operasi
│   ├── bra/                 # Modul Berita Radio SAR
│   │   ├── form.php                  # Builder telegram radio Senkom & live word counter
│   │   ├── list.php                  # Arsip dokumen telegram (MAPEL, SAR, PERGERAKAN KAPAL)
│   │   ├── view.php                  # Lembar cetak fisik telegram kedinasan Senkom & QR barcode
│   │   └── verify_invalid.php        # Tampilan dokumen tidak sah / token manipulasi
│   ├── master/              # Modul Master Data
│   │   ├── index.php                 # Tampilan utama master data
│   │   └── partials/                 # Tab armada alut, pejabat SMC, potensi SAR, dan modals
│   ├── admin/               # Modul Administrasi & Satker
│   │   ├── index.php                 # Tampilan utama dashboard administrasi
│   │   └── partials/                 # Tab satker, manajemen user, backup/restore, dan audit log
│   ├── auth/                # Modul Otentikasi
│   │   └── login.php                 # Portal login petugas SAR
│   ├── layout/              # Template Tata Letak Bersama
│   │   ├── header.php, sidebar.php, navbar.php, footer.php
│   │   ├── public_header.php, public_footer.php # Layout khusus portal verifikasi publik mandiri
│   └── errors/              # Halaman HTTP Error Terstandarisasi (403.php, 404.php, 500.php)
├── api/                     # RESTful JSON API Backend
│   ├── controllers/         # API Controllers per Domain
│   │   ├── BaseApiController.php, AuthApiController.php, OperasiApiController.php
│   │   ├── RenopsApiController.php, SitrepApiController.php, BraApiController.php
│   │   ├── MasterApiController.php, SettingsApiController.php, GisApiController.php, AiApiController.php
│   └── index.php            # API Front Controller & request dispatcher
├── assets/                  # Static Web Assets (CSS, JS, Fonts, Images)
│   ├── vendor/              # Third-Party Libraries Offline (Leaflet, SweetAlert2, QRCode.js, Tailwind)
│   ├── js/                  # Application JavaScript
│   │   ├── pdf_document_exporter.js  # Engine PDF resmi dengan algoritma safe page-cut multi-halaman
│   │   ├── gis_master.js             # Workstation GIS lengkap, Studio Capture 16:9, rute, IAMSAR
│   │   ├── gis.js                    # TacticalGisMap Leaflet wrapper class
│   │   ├── app.js                    # Theme switcher, CSRF header hook, modal system
│   │   └── pages/                    # Browser-cacheable page-specific script handlers
│   ├── css/
│   │   └── style.css        # Basarnas Design System, Glassmorphism, Dark Mode, Print Sheet CSS
│   └── images/              # Logo resmi Basarnas, ikon maritim & grafis instansi
├── database/                # Skrip Migrasi & Pangkalan Data
│   └── migrate.php          # CLI Database Migration Runner (Zero runtime locking, auto-increment sync)
├── storage/                 # Direktori Penyimpanan Terproteksi (.htaccess blocked)
│   ├── logs/                # Berkas log aplikasi & audit trail
│   ├── backups/             # Berkas cadangan dump SQL & snapshot JSON
│   └── cache/               # Cache sementara sistem
├── tests/                   # Automated Regression Test Suites (39+ Unit & E2E Tests)
│   ├── run_all.php                   # Master Test Runner CLI (menjalankan seluruh test suite)
│   ├── test_buttons_and_navigation_e2e.php  # Verifikasi navigasi & tombol antarmuka
│   ├── test_crud_audit_end_to_end.php       # Verifikasi siklus CRUD lengkap & pencatatan audit log
│   ├── test_doc_verify_and_barcode.php      # Verifikasi token HMAC & Barcode QR keabsahan dokumen
│   ├── test_document_map_snapshot.php       # Verifikasi tangkapan peta 16:9 & tabel komponen dokumen
│   ├── test_gis_advanced_and_bra_dtg.php    # Verifikasi GIS lanjutan, IAMSAR, & format DTG BRA
│   ├── test_lkp_routing.php                 # Verifikasi rute pelayaran berkecepatan knot kapal
│   ├── test_master_data_integrity.php       # Verifikasi integritas data master alut, potensi, pejabat
│   ├── test_phase4_refactoring_architecture.php # Verifikasi pemisahan Controllers, Services, Helpers
│   └── ...                           # Rangkaian uji regresi lengkap lainnya
├── cli/                     # Skrip Bantu Konsol
│   └── sync_assets.php      # Sinkronisasi symlink/mirror aset publik (/assets -> /public/assets)
├── config/                  # Konfigurasi Inti
│   ├── app.php              # Bootstrap inti, session, CSRF guard, autoloader, zona waktu WITA
│   └── database.php         # PDO Database Singleton connection & auto-reconnect
├── .env.example             # Contoh berkas konfigurasi kredensial lingkungan
├── .htaccess                # Konfigurasi Apache webroot hardening & rewrite
└── metadata.json            # Metadata kapabilitas aplikasi (Gemini API & SAR System)
```

---

## 🚀 Panduan Menjalankan Aplikasi

### 1. Prasyarat Sistem

- **PHP** versi 8.0, 8.1, 8.2, 8.3, atau 8.4+ (Ekstensi `pdo_mysql`, `json`, `mbstring`, `openssl` wajib aktif).
- **MySQL** versi 5.7/8.0+ atau **MariaDB** 10.4+.
- Web Server: Built-in PHP Server, Apache (XAMPP/Laragon/MAMP), atau Nginx/LiteSpeed.

### 2. Konfigurasi Lingkungan (`.env`)

Salin file `.env.example` menjadi `.env` pada direktori root proyek:

```bash
cp .env.example .env
```

Sesuaikan parameter basis data pada berkas `.env`:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qrsar
DB_USERNAME=root
DB_PASSWORD=
APP_ENV=development
APP_KEY=SAR-304-KENDARI-SECRET-KEY
```

### 3. Migrasi Otomatis Skema Basis Data

Sistem QR-SAR dilengkapi **CLI Database Migration Runner** yang secara otomatis membuat dan menyinkronkan seluruh tabel pangkalan data, indeks komposit, data awal 9 Pangkalan SarUnit Sultra, serta penataan auto-increment tanpa perlu import file SQL manual:

```bash
# Pastikan database sudah dibuat di MySQL:
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS qrsar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Jalankan skrip migrasi terpusat:
php database/migrate.php
```

### 4. Menjalankan Server Lokal (Development)

Aplikasi QR-SAR mendukung dua opsi eksekusi:

#### Opsi A: Mode Cepat (Root Document Root)
Sangat praktis untuk pengujian di laptop dinas menggunakan PHP Built-in Server:

```bash
php -S 127.0.0.1:8000 router.php
```

#### Opsi B: Mode Standar Produksi (Public Document Root)
Direkomendasikan untuk keamanan maksimal pada server produksi (seluruh kode privat berada di luar Document Root):

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

#### Manajemen Aset Publik (Single Source of Truth)
Aset desain statis berpusat pada folder `/assets`. Folder `/public` secara otomatis terhubung melalui tautan simbolik (*symlink*) dengan fallback cerdas. Gunakan CLI helper jika diperlukan:

```bash
# Periksa status keterhubungan aset:
php cli/sync_assets.php --status

# Pasang / perbarui tautan simbolik relatif (Linux/macOS):
php cli/sync_assets.php --symlink

# Sinkronisasi salinan fisik penuh (Windows / Hosting tanpa symlink):
php cli/sync_assets.php --mirror
```

Buka peramban (*browser*) dan akses:  
👉 **`http://localhost:8000`**

---

## 🔐 Akun Masuk Sistem (Default Credentials)

Sistem dilengkapi dua tingkatan peran (*Role-Based Access Control*):

1. **Administrator Utama (SMC)**
   - **Username**: `admin`
   - **Password**: `Kendari304` *(atau `admin123`)*
   - **Kewenangan**: Hak penuh mengelola Operasi, menerbitkan naskah BRA, menghapus arsip, konfigurasi master alut/tim/pejabat, profil satker, backup database, dan inspeksi audit log.

2. **Operator Siaga SAR & Senkom**
   - **Username**: `user`
   - **Password**: `Kendari304` *(atau `user123`)*
   - **Kewenangan**: Entri Laporan Awal, pembuatan Renops, Sitrep harian, drafting Berita Radio SAR, dan plotting peta taktikal GIS.

---

## 📑 Spesifikasi Fitur Utama

### 1. Inisiasi Laporan Awal (Emergency Quick Report)
- Format baku pesan darurat siaga SAR respons awal resmi Basarnas.
- Konversi koordinat instan dua arah antara Derajat Menit Detik (DMS) dan Angka Desimal.
- Kalkulasi otomatis jarak Nautical Miles (NM) / Kilometer dan radial arah derajat kompas dari pangkalan/pos tim yang diberangkatkan ke titik LKP beserta perkiraan waktu tempuh (*Estimated Time of Arrival / ETA*).

### 2. Tabbed Hub Operasi SAR
- **Tab Kronologi & Hierarki**: Timeline hierarkis terpadu runtut waktu (Laporan Awal, Renops H.X, Sitrep H.X, Penutupan) yang dihitung secara dinamis dari tanggal kejadian.
- **Tab Rencana Operasi (RENOPS)**: Penyusunan Renops harian dengan grid pembagian SRU 1 & SRU 2, batas koordinat area pencarian, alokasi alut, dan frekuensi radio.
- **Tab Laporan Perkembangan (SITREP)**: Entri harian hasil pencarian, cuaca BMKG maritim, faktor penghambat, dan rekapitulasi korban.
- **Tab Matriks Korban**: Visualisasi status korban terkini (*Selamat, Meninggal Dunia, Dalam Pencarian, Hilang*) dengan kalkulasi otomatis.
- **Tab Kekuatan Personel & Alut**: Ringkasan kesiapan personel rescuer, potensi SAR gabungan (TNI/Polri/Masyarakat), dan alut yang dikerahkan.
- **Tab Berita Radio SAR Terkait**: Akses langsung ke naskah telegram radio yang tertaut dengan operasi.
- **Tab Cetak Dokumen & Snapshot**: Pusat komando pencetakan PDF resmi (Laporan Awal, Renops, Sitrep, Penutupan) dan manajemen studio capture peta 16:9.
- **Tutup & Rollback Operasi**: Fitur penutupan resmi operasi dan pengembalian (*rollback*) status jika terdapat informasi korban lanjutan.

### 3. Berita Radio SAR (BRA) Standar Senkom
- Kategori dokumen: `BRA MAPEL` (Radio Siaran Informasi), `BRA SAR` (Operasi SAR), dan `BRA PERGERAKAN KAPAL` (Pelayaran KN SAR / Rescue Boat).
- Penomoran atomik resmi: `[NOMOR_URUT]/SAR-304/[BULAN][TAHUN]`.
- Seksi dinas alfabetis `AAA`, `BBB`, `CCC`, dst., dengan penghitung kata (*live word counter*) otomatis.
- Format cetak persis dokumen fisik kedinasan Senkom:
  - Header kop logo & instansi resmi.
  - Label vertikal kiri: `RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM`.
  - Tabel 5 kolom Senkom (Panggilan, Jenis, Nr. Stn, Derajat, Instruksi penerusan).
  - Bagian DARI, AKSI, INFO, TWU, JUMLAH KATA, NO, MENBRA, PERIHAL.
  - Tanda tangan pengirim (SMC) disahkan dengan **Barcode QR Digital Terenkripsi** berlogo Basarnas di tengah.
  - Tabel 4 kolom buku komunikasi operator Senkom (T.W.T, T.W.K, SISTEM, PARAF).

### 4. Verifikasi Keabsahan Dokumen Digital (HMAC QR Code)
- Setiap dokumen operasional SAR (Laporan Awal, Renops, Sitrep, Penutupan, dan BRA) memiliki token keabsahan digital yang dienkripsi menggunakan algoritma HMAC-SHA256.
- Barcode QR pada dokumen cetak langsung menautkan publik ke portal verifikasi resmi:  
  `http://domain/operasi.php?action=verify_public&token=...`
- Menampilkan rincian integritas dokumen: Judul Resmi, Nomor Dokumen, Timestamp Pengesahan, Nama Pejabat SMC penandatangan, NIP, Pangkat/Jabatan, dan Status Keabsahan Elektronik tanpa mewajibkan pemindai untuk masuk (*login*).

### 5. Studio Capture Peta Taktis 16:9 & Map Snapshot
- Mode penangkapan layar peta pada antarmuka GIS dengan panduan bingkai rasio 16:9.
- Algoritma spasial mendeteksi seluruh komponen taktis yang berada di dalam area tangkapan (titik LKP, lokasi korban, poligon sektor pencarian, rute pergerakan kapal).
- Menghasilkan gambar peta beresolusi tinggi (1280x720) yang tersimpan pada tabel `DokumenPetaSnapshot` dan tersemat rapi pada lembar cetak Laporan Awal, Renops, Sitrep, dan Penutupan Operasi disertai **Tabel Keterangan Komponen**.

### 6. Tactical Maritime GIS & Generator Pola IAMSAR
- Pemetaan maritim interaktif Leaflet dengan citra Satelit Resolusi Tinggi (Esri World Imagery) dan Peta Vektor OSM.
- Plotting titik taktis: Titik LKP, Kantor Kansar, Pos SAR, Dermaga Sandar, Helipad, Titik Temu Korban.
- Perhitungan luas poligon pencarian (*Search Area*) dalam Nautical Miles persegi (NM²) dan Kilometer persegi (Km²).
- Generator pola pencarian standar maritim **IAMSAR (International Aeronautical and Maritime Search and Rescue)**:
  - *Parallel Sweep Search (PS)*
  - *Creeping Line Search (CS)*
  - *Expanding Square Search (SS)*
  - *Sector Search (VS)*
- Pengukur jarak pelayaran multi-titik (*distance & azimuth bearing*) dengan estimasi durasi tempuh berdasarkan kecepatan knot armada kapal Basarnas (KN SAR Pacitan 18 knot, RB 210 20 knot, RIB 25 knot, Rubber Boat 10 knot).
- Ekspor data spasial ke format **GeoJSON** dan **KML (Google Earth)**.

### 7. Official PDF Exporter (Anti-Cut Smart Slicing)
- Mengonversi lembar naskah dinas HTML menjadi berkas PDF A4 Portrait resmi.
- Algoritma pemindaian baris piksel kanvas (*safe page-cut detection*) mencegah pemotongan baris teks atau huruf saat pergantian halaman.
- Margin presisi kedinasan: Halaman 1 (Top 10mm, Bottom 12mm), Halaman 2+ (Top 15mm, Bottom 12mm).

---

## 🧪 Pengujian Otomatis (Automated Test Suites)

Proyek ini dilengkapi **39+ automated test suites** berbasis PHP CLI yang mencakup pengujian unit, integrasi arsitektur, kalkulasi spasial, dan alur end-to-end:

```bash
# Menjalankan seluruh test suite secara otomatis:
php tests/run_all.php

# Atau menjalankan modul pengujian spesifik:
php tests/test_phase4_refactoring_architecture.php
php tests/test_doc_verify_and_barcode.php
php tests/test_crud_audit_end_to_end.php
php tests/test_lkp_routing.php
```

---

## 🛡️ Keamanan & Standar Koding

- **PHP 8 Strict Typing** (`declare(strict_types=1);` di seluruh berkas controller, model, service, dan helper).
- **CSRF Token Protection** di seluruh formulir mutasi data.
- **PDO Prepared Statements** untuk mencegah serangan SQL Injection secara menyeluruh.
- **XSS Prevention** melalui escaping output (`htmlspecialchars`).
- **Role-Based Access Control (RBAC)** untuk melindungi aksi penghapusan dan perubahan data vital.
- **HMAC SHA-256 Cryptographic Tokens** untuk keabsahan barcode QR dokumen digital.

---

**Basarnas KPP Kendari &bull; AVIGNAM JAGAT SAMAGRAM &bull; Semoga Selamatlah Alam Semesta**

