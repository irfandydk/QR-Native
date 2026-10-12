# QR-SAR (Quick Report Search and Rescue) - PHP Native & MySQL
### Sistem Pelaporan Operasi SAR & Berita Radio SAR (FILE AKTIF)
**Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari — Badan Nasional Pencarian dan Pertolongan (BASARNAS)**

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
├── config/
│   ├── app.php              # Bootstrap aplikasi, session, CSRF, WITA timezone, BASE_URL
│   └── database.php         # PDO Database Singleton connection & auto fallback
├── helpers/
│   ├── AuthHelper.php       # Role guard (ADMIN/USER), session management & izin aksi
│   ├── TemplateHelper.php   # Generator teks format WhatsApp resmi FILE AKTIF Basarnas
│   ├── BraHelper.php        # Nomor urut atomik telegram ([urut]/SAR-304/[MMYY]), kalkulasi kata
│   ├── GeoHelper.php        # DMS <-> Decimal, Haversine NM/Km, Azimuth/Bearing, Waypoint math
│   └── AiHelper.php         # Integrasi asisten AI Gemini & generator laporan operasi
├── models/
│   ├── OperasiModel.php     # CRUD Operasi, penutupan operasi, statistik KPI & relasi data
│   ├── RenopsModel.php      # Rencana Operasi harian (H.1, H.2, dst.) & pembagian sektor SRU
│   ├── LaporanModel.php     # SITREP harian, status korban (S/MD/DP/H), penutupan ops
│   ├── BraModel.php         # Dokumen naskah telegram radio, nomor urut atomik & seksi AAA-ZZZ
│   ├── MasterModel.php      # Master Alut/Palsar, Tim Potensi SAR, SarUnit, Pejabat SMC
│   ├── GisModel.php         # Data titik taktis, poligon pencarian, rute & pola IAMSAR
│   └── SettingsModel.php    # Profil Satker, Pejabat, ganti password, export SQL & backup JSON
├── views/
│   ├── layout/
│   │   ├── header.php       # HTML5 head, SEO meta, token CSRF, Google Fonts, Tailwind/CSS
│   │   ├── sidebar.php      # Navigasi utama sidebar responsif dengan badge status
│   │   ├── navbar.php       # Top header, indikator satker SAR-304, dark mode switcher, profil
│   │   └── footer.php       # Footer kedinasan, modal umum, script global
│   ├── dashboard.php        # Beranda ringkasan operasi aktif, KPI cards, filter status
│   ├── operasi_form.php     # Form inisiasi laporan awal operasi & kalkulator koordinat
│   ├── operasi_detail.php   # Tabbed Hub (Ringkasan, Renops, Sitrep, BRA Terkait, GIS, Cetak)
│   ├── bra_list.php         # Arsip dokumen BRA (MAPEL, SAR UMUM, PERGERAKAN KAPAL)
│   ├── bra_form.php         # Form builder telegram Senkom, auto-rekomendasi & word counter
│   ├── bra_view.php         # Lembar fisik resmi telegram Basarnas (Kop, Senkom, Barcode QR)
│   ├── gis.php              # Antarmuka GIS maritim taktis & pola pencarian IAMSAR
│   ├── master_settings.php  # Tab alut, tim, pejabat, satker, keamanan, backup & restore
│   └── login.php            # Portal login otentikasi petugas SAR
├── api/
│   └── index.php            # RESTful JSON Router untuk AJAX frontend & integrasi eksternal
├── database/
│   ├── db_sar_qr.sql        # Standalone dump MySQL lengkap DDL + data siap import
│   └── seed.sql             # Skrip SQL data awal lengkap
├── assets/
│   ├── css/style.css        # Desain sistem Basarnas, dark/light theme, komponen & cetak
│   ├── js/app.js            # Inisialisasi theme, toast alert, modal & utility
│   ├── js/bra.js            # Handler seksi telegram dinamis, kalkulator kata & barcode canvas
│   ├── js/gis.js            # Engine Leaflet taktis maritim & kalkulator IAMSAR
│   └── images/
│       └── logo_basarnas.svg # Lambang resmi vektor SVG Basarnas
├── index.php                # Controller root (Dashboard)
├── operasi.php              # Controller CRUD Operasi SAR
├── operasi_detail.php       # Controller detail tabbed hub operasi
├── operasi_form.php         # Controller form inisiasi operasi
├── bra.php                  # Controller arsip & penerbitan dokumen BRA
├── gis.php                  # Controller Tactical GIS & map plotting
├── settings.php             # Controller pengaturan & master data
├── master.php               # Forwarder master data
├── login.php                # Controller login petugas
└── logout.php               # Controller logout
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

### 4. Menjalankan Server Lokal
Cukup jalankan perintah built-in web server PHP dari direktori root proyek:
```bash
php -S 127.0.0.1:8000
```
Buka browser dan akses alamat:
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

### 1. Inisiasi Laporan Awal & Penomoran Resmi
- Penomoran otomatis berformat dinas Basarnas: `NO: LA-[TANGGAL]-[URUT]/OPS-SAR/[KODE_SATKER]/[BULAN][TAHUN]` (contoh: `LA-210926-001/OPS-SAR/SAR-304/0926`).
- Konversi koordinat instan antara Derajat Menit Detik (DMS) dan Angka Desimal.
- Kalkulasi otomatis jarak Nautical Miles (NM) dan Kilometer dari Kantor SAR Kendari ke titik LKP beserta perkiraan waktu tempuh (*Estimated Time of Arrival / ETA*).

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
