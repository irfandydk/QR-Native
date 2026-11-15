# Verifikasi Laporan Audit QR-SAR

**Tanggal verifikasi:** 15 November 2026
**Dokumen yang diverifikasi:** `audit-qr-sar.md` (Laporan Audit Kode & Keamanan Cybersecurity — QR-SAR, 24 temuan)
**Metode:** Membaca kode sumber terkini, termasuk perubahan di working tree yang belum di-commit. Aplikasi, tes, dan database tidak dijalankan. Nomor baris mengacu ke kode saat ini dan bisa berbeda dari nomor di laporan asli.

## Legenda

**Status klaim**

- **BENAR**: masalahnya ada di kode, sesuai uraian.
- **SEBAGIAN**: masalahnya ada, tetapi uraian dampak atau sebagiannya keliru.
- **LATEN**: kodenya rapuh, tetapi saat ini tidak bisa dipicu dari luar.
- **KELIRU**: klaim utama tidak sesuai kode atau bertentangan dengan desain yang disengaja.

**Risiko bila saran perbaikan diikuti apa adanya**

- **Aman**: perubahan kecil dan tidak mengubah perilaku yang sudah berjalan.
- **Hati-hati**: aman bila dikerjakan dengan cara yang dijelaskan di dokumen ini.
- **Berisiko**: saran asli akan merusak fitur yang sedang berjalan.

---

## Ringkasan

Dari 24 temuan:

- 5 temuan benar dan perbaikannya aman: #1, #17, #18, #20, #21.
- 12 temuan benar sebagian atau laten. Perbaikannya berguna bila mengikuti cara yang disarankan di dokumen ini.
- 1 temuan keliru karena bertentangan dengan spesifikasi proyek: #3.
- 6 saran perbaikan akan **mengganggu kestabilan** bila diikuti apa adanya: #3, #7, #13, #23 (bagian pertama), #24 (token), #10 (masking menyeluruh).

Tingkat "Perlu Tambalan Segera" di laporan asli terlalu tinggi. Sebagian besar celah keamanan hanya bisa dipicu oleh ADMIN, butuh konfigurasi server tertentu, atau sudah punya mitigasi di kode (CSP sandbox di `uploads/.htaccess`, gerbang wajib ganti sandi, token HMAC dengan `APP_KEY` rahasia).

| # | Temuan | Status | Manfaat perbaikan | Risiko saran asli | Rekomendasi singkat |
|---|---|---|---|---|---|
| 1 | `trim()` sandi tidak sinkron | BENAR | Tinggi | Aman | `trim()` sandi baru di `changePassword()` |
| 2 | Validator restore SQL | SEBAGIAN | Sedang | Hati-hati | Ketatkan DROP/TRUNCATE, tambah lock file |
| 3 | RBAC hapus foto RENOPS | KELIRU | – | **Berisiko** | Jangan diubah, ini desain K6/K13 |
| 4 | SVG denylist, URL logo, hapus ikon | SEBAGIAN | Sedang | Hati-hati | Header CSP di Apache, nama ikon persis, LIKE di-escape |
| 5 | Proxy tile | SEBAGIAN | Rendah–sedang | Aman | `follow_location=0`, cek gambar, https saja |
| 6 | `captured_by` selalu `0` | BENAR | Rendah | Hati-hati | Migrasi kolom di tiga tempat sekaligus |
| 7 | Kriptografi token BRA/QR | KELIRU (klaim utama) | Rendah | **Berisiko** | Jangan ubah rumus token |
| 8 | Sandi reset `Kendari304` | SEBAGIAN | Sedang | Hati-hati | Isi `DEFAULT_RESET_PASSWORD` per situs |
| 9 | CSRF tidak diputar, CSRF via GET | SEBAGIAN | Rendah | Aman | Hapus jalur `$_GET`, reset token saat login |
| 10 | `getMessage()` bocor, operator `@` | SEBAGIAN | Rendah | **Berisiko** (masking menyeluruh) | Sembunyikan hanya `PDOException` |
| 11 | CSP, escaping, `extract()` | SEBAGIAN | Rendah | Hati-hati | Perbaiki escape ganda judul saja |
| 12 | Host header ke `BASE_URL` | BENAR (konfigurasi) | Rendah | Hati-hati | Daftar host yang diizinkan, bukan satu `APP_URL` |
| 13 | DDL runtime | BENAR (fakta) | Rendah | **Berisiko** | Pertahankan, sudah disengaja |
| 14 | Duplikasi PDF service | BENAR | Rendah | Hati-hati | Tunda, ada perubahan belum di-commit |
| 15 | Tanpa paginasi | SEBAGIAN | Rendah | Hati-hati | Muat `getDbStats()` hanya di tab backup |
| 16 | Arsitektur non-PSR | BENAR (fakta) | Rendah | Berisiko bila sekaligus | Opsional: PHPStan tanpa ubah kode |
| 17 | Google Fonts | BENAR | Rendah | Aman | Bundel font lokal |
| 18 | Kode mati backup JSON | BENAR | Rendah | Aman | Hapus berikut tesnya, perhatikan rentang baris |
| 19 | `ini_parse_quantity()` PHP 8.2 | SEBAGIAN | Rendah | Aman | Ubah syarat di README ke PHP 8.2+ |
| 20 | Path traversal `removeImageFile` | LATEN | Rendah | Aman | Tolak `..` |
| 21 | Transaksi bocor | BENAR | Rendah | Aman | `rollBack()` sebelum `return false` |
| 22 | ID pasal BRA bergeser | SEBAGIAN | Rendah | Aman | Salin pola pre-claim |
| 23 | Sidik sesi sebelum commit | SEBAGIAN | Rendah | **Berisiko** | Jangan pindahkan; pulihkan sidik lama di `catch` |
| 24 | Kunci penomoran BRA, token 64-bit | SEBAGIAN | Rendah | **Berisiko** (token) | Jangan perpanjang token tanpa migrasi |

---

## Peringatan stabilitas: saran yang jangan diikuti apa adanya

### A. #3: menambah `canDelete()` pada hapus foto RENOPS akan memutus alur kerja USER

Larangan ini bertentangan dengan spesifikasi yang disengaja:

- Komentar kelas di [RenopsDokumenApiController.php:4](../api/controllers/RenopsDokumenApiController.php#L4): "Semua pengguna login boleh menyunting/menerbitkan (K13)".
- Spesifikasi [2026-11-06-renops-dokumen-smeac-design.md:53](superpowers/specs/2026-11-06-renops-dokumen-smeac-design.md#L53) (K13) dan baris 175: "foto bisa dikeluarkan dari draf".
- UI menampilkan tombol hapus foto untuk semua user di [renops_dokumen.js:532](../assets/js/pages/renops_dokumen.js#L532) dan [renops_dokumen.js:568](../assets/js/pages/renops_dokumen.js#L568).
- Foto yang dipakai versi terbit disalin ke folder versi itu (spesifikasi baris 152 dan 182). Menghapus foto dari draf tidak menghapus bukti di dokumen terbit.

Jika `canDelete()` ditambahkan, USER akan mendapat error 403 saat mengelola foto draf. Ubah aturan ini hanya bila kebijakan kantor memang berubah, dan ubah UI-nya bersamaan.

### B. #7: mengubah rumus atau panjang token akan membuat semua QR tercetak tidak valid

- [DocVerifyHelper.php:7](../helpers/DocVerifyHelper.php#L7) menyatakan "rumus token tidak boleh diubah", supaya QR lama tetap bisa dipindai.
- Token BRA dihitung ulang saat verifikasi dan URL-nya disimpan di `barcode_data`. Mengganti `md5` atau panjang HMAC membuat setiap naskah yang sudah dicetak gagal diverifikasi.

### C. #13: memindahkan DDL runtime ke migrasi akan merusak restore cadangan lama

- [BackupService.php:630-635](../services/BackupService.php#L630) sengaja memanggil `ensureSchemaUpdated(true)` setelah restore. Dump lama menghapus kolom baru, sedangkan tabel migrasi tetap mencatat migrasinya sudah jalan.
- [SettingsModel.php:65-66](../models/SettingsModel.php#L65) sengaja memakai `SHOW COLUMNS` agar simpan pengaturan tidak error 500 di server yang belum menjalankan migrasi.

### D. #23: memindahkan `refreshCredentialFingerprint()` ke setelah `commit()` membuat user langsung ter-logout

Urutannya disengaja, lihat komentar di [SettingsModel.php:327](../models/SettingsModel.php#L327):

1. `UPDATE Users` menulis hash baru. Koneksi yang sama sudah melihat hash ini walau belum di-commit.
2. `AuditHelper::log()` memanggil `getCurrentUser()`, lalu `isLoggedIn()`.
3. `isLoggedIn()` membandingkan sidik di sesi dengan hash di DB. Jika sidik belum diperbarui, sesi dikosongkan di [AuthHelper.php:47-50](../helpers/AuthHelper.php#L47).

Dampak urutan saat ini pada skenario gagal (rollback) ringan: user hanya perlu login ulang dengan sandi lama. Bila tetap ingin dirapikan, simpan sidik lama sebelum `try` dan kembalikan di blok `catch`.

### E. #24: memperpanjang token RENOPS ke 32 hex akan menggagalkan penerbitan

- Kolom `token` bertipe `CHAR(16)` di [2026_11_06_create_renops_dokumen.sql:15](../database/migrations/2026_11_06_create_renops_dokumen.sql#L15) dan [2026_10_28_create_dokumen_token.sql:11](../database/migrations/2026_10_28_create_dokumen_token.sql#L11).
- Dalam strict mode MySQL, token 32 karakter memicu error `Data too long` dan tombol "Terbitkan QR" gagal.
- Token 64-bit tidak praktis ditebak secara online, jadi manfaat perubahan ini kecil.

### F. #10: mengganti semua `$e->getMessage()` dengan pesan umum akan menghilangkan pesan validasi bisnis

- Model melempar pesan untuk pengguna sebagai `RuntimeException`, misalnya "Operasi SAR telah berstatus DITUTUP (CLOSED)..." di [LaporanModel.php:178](../models/LaporanModel.php#L178) dan [LaporanModel.php:305](../models/LaporanModel.php#L305).
- `PDOException` adalah turunan `RuntimeException`. Saringan berdasarkan `RuntimeException` tidak bisa membedakan keduanya.
- Cara aman: sembunyikan pesan hanya bila `$e instanceof PDOException` dan `APP_DEBUG` mati.
- Jangan menghapus operator `@` secara massal. Warning yang tercetak bisa merusak respons JSON dan PDF, atau memicu error "headers already sent".

---

## Rincian per temuan

### Prioritas 1 laporan asli

#### #1 — `trim()` sandi tidak sinkron · BENAR · Aman

- Login memangkas sandi di [AuthHelper.php:320](../helpers/AuthHelper.php#L320). Ganti sandi admin juga memangkas, di [AdminController.php:95](../controllers/AdminController.php#L95).
- Profil web [AuthController.php:96](../controllers/AuthController.php#L96) dan API [AuthApiController.php:46-48](../api/controllers/AuthApiController.php#L46) tidak memangkas. `changePassword()` meng-hash nilai mentah di [SettingsModel.php:309](../models/SettingsModel.php#L309).
- Akibatnya, sandi baru `" sandi123 "` tersimpan apa adanya, lalu login berikutnya gagal.
- Koreksi kecil pada laporan: kunciannya tidak permanen untuk USER, karena admin bisa mereset akun. Untuk akun ADMIN tunggal yang berganti sandi lewat API, pemulihannya harus lewat DB.
- **Perbaikan:** di awal `changePassword()`, tambahkan `$newPass = trim($newPass);` sebelum cek panjang di baris 301. Jangan hapus `trim()` dari login. Sertakan satu tes: ganti sandi ke `" abcdefgh "`, lalu login dengan `"abcdefgh"` harus berhasil.

#### #2 — Validator restore SQL · SEBAGIAN · Hati-hati

- Benar: regex nama tabel `([A-Za-z0-9_]+)` berhenti di koma ([BackupService.php:560](../services/BackupService.php#L560)), dan sisa statement tidak diperiksa ([BackupService.php:570-590](../services/BackupService.php#L570)). Jadi `DROP TABLE IF EXISTS Users, TabelLain` lolos. Hal yang sama berlaku untuk `CREATE TABLE ... SELECT` dan `INSERT ... SELECT`.
- Konteks yang tidak disebut laporan: restore hanya bisa dilakukan ADMIN dengan CSRF. Ancaman realistisnya adalah berkas cadangan yang rusak atau sengaja dimodifikasi, lalu diunggah admin. Menghapus `mysql.user` butuh hak DB yang biasanya tidak dimiliki akun aplikasi.
- Benar: tidak ada lock antar-proses, dan kegagalan rollback hanya dicatat di log ([BackupService.php:655-657](../services/BackupService.php#L655)). Dengan satu akun ADMIN, risiko konkurensinya rendah, misalnya klik ganda.
- **Perbaikan aman:**
  1. Untuk `DROP` dan `TRUNCATE`, sisa statement setelah nama tabel harus kosong. Dump resmi selalu berbentuk ``DROP TABLE IF EXISTS `T`;`` ([BackupService.php:328](../services/BackupService.php#L328)).
  2. Tolak nama tabel yang diikuti `.` (lintas database).
  3. Pasang `flock()` pada `storage/backups/restore.lock`.
- **Jangan** menambah pemindaian kata kunci seperti `SELECT` pada isi `INSERT`. Denylist yang sudah ada di [BackupService.php:527](../services/BackupService.php#L527) ikut memindai data. Narasi operasi yang kebetulan memuat kata seperti "GRANT" bisa menggagalkan restore cadangan yang sah. Menambah kata kunci memperbesar risiko ini.

#### #3 — RBAC hapus foto RENOPS · KELIRU · Berisiko

Lihat bagian A di atas. Pernyataan bahwa auth diserahkan ke tiap controller memang benar. Namun itu pola desain proyek: CSRF ditegakkan terpusat di `api/index.php`, autentikasi per controller. Itu bukan bug.

#### #4 — SVG, URL logo, hapus ikon · SEBAGIAN · Hati-hati

- Benar: denylist SVG di [AdminController.php:300](../controllers/AdminController.php#L300) dan [GisApiController.php:617](../api/controllers/GisApiController.php#L617) tidak menangkap `onbegin`, `onfocus`, dan atribut serupa.
- Belum disebut laporan: upload ikon GIS terbuka untuk semua user login, karena hanya memakai `requireAuth()` ([GisApiController.php:249-252](../api/controllers/GisApiController.php#L249)). Upload logo khusus admin.
- Mitigasi yang sudah ada:
  - [uploads/.htaccess](../uploads/.htaccess) mengirim `Content-Security-Policy: ... sandbox` dan `nosniff`.
  - `public/uploads` adalah symlink ke `../uploads`, jadi `.htaccess` itu tetap berlaku di Apache bila `AllowOverride All` ([apache.conf.sample:21](../apache.conf.sample#L21)) dan `mod_headers` aktif.
  - Sample Nginx sudah memasang header yang sama ([nginx.conf.sample:48](../nginx.conf.sample#L48)).
  - Di aplikasi, ikon dirender sebagai `<img>` atau ikon Leaflet, sehingga skrip SVG tidak berjalan.
  - Celah ini baru berbahaya bila berkas dibuka langsung di server tanpa header tersebut.
- Klaim hapus `.htaccess` praktis tidak bisa dieksploitasi. Folder `uploads/icons/` tidak punya `.htaccess` sendiri, dan `/` ditolak regex sehingga `uploads/.htaccess` tidak terjangkau.
- **Bug nyata yang terlewat:** pola `LIKE '%' . $filename . '%'` di [GisApiController.php:689-690](../api/controllers/GisApiController.php#L689). Nama berkas `.` lolos regex, lalu `%.%` mereset ikon kustom di **semua** titik peta. Hanya admin yang bisa memicunya, tetapi kerusakan data bisa terjadi tidak sengaja.
- **Perbaikan:**
  1. Tambahkan header CSP sandbox ke blok `<Directory .../uploads>` di `apache.conf.sample`.
  2. Batasi nama berkas yang boleh dihapus ke format hasil upload, `^icon_\d+_[0-9a-f]{8}\.(png|jpe?g|svg|webp)$`.
  3. Cocokkan URL dengan akhiran persis, bukan `%nama%`.
- Membatasi ikon hanya ke raster mengubah UX bagi user yang memakai SVG, jadi konfirmasi dulu ke pengguna. Ikon SVG lama tetap tampil.

#### #5 — Proxy tile · SEBAGIAN · Aman

- Benar: `http` diizinkan ([GisApiController.php:802](../api/controllers/GisApiController.php#L802)), redirect diikuti karena `file_get_contents` default, dan `Content-Type` dipaksa `image/png` tanpa cek isi.
- Keliru: cache tidak permanen. Ada TTL 7 hari ([GisApiController.php:812](../api/controllers/GisApiController.php#L812)). Yang benar: berkas kedaluwarsa tidak pernah dihapus, sehingga disk terus bertambah.
- Keliru sebagian: respons HTTP 4xx/5xx tidak ikut di-cache, karena `file_get_contents` mengembalikan `false`. Hanya respons 200 yang berisi HTML yang ikut tersimpan.
- Risiko SSRF kecil, karena host dibatasi allowlist (Google, OSM, ArcGIS). Redirect hanya berbahaya bila penyedia tersebut mengalihkan ke alamat internal.
- **Perbaikan aman:**
  1. Tambahkan `'follow_location' => 0` ke context.
  2. Cek isi dengan `getimagesizefromstring()` sebelum menulis cache.
  3. Batasi skema ke `https`. Semua URL tile di `gis_master.js` sudah `https://`, contohnya [gis_master.js:1157](../assets/js/gis_master.js#L1157).
  4. Opsional: pembersihan cache lama lewat CLI.

#### #6 — `captured_by` selalu `0` · BENAR · Hati-hati

- Benar: `(int)'USR-ADMIN'` bernilai `0` ([DokumenPetaSnapshotModel.php:160](../models/DokumenPetaSnapshotModel.php#L160)).
- Dampak rendah: kolom ini tidak pernah dibaca oleh fitur mana pun. Hanya jejak auditnya yang hilang.
- **Jebakan stabilitas:** jenis kolom didefinisikan di tiga tempat:
  1. `CREATE TABLE` runtime di [DokumenPetaSnapshotModel.php:43](../models/DokumenPetaSnapshotModel.php#L43).
  2. [0000_base_schema.sql:156](../database/migrations/0000_base_schema.sql#L156).
  3. Migrasi baru untuk `ALTER`.
- Bila cast `(int)` dihapus tetapi kolomnya masih `INT`, misalnya setelah restore dump lama, menyimpan `'USR-ADMIN'` di strict mode memicu error `Incorrect integer value` dan **simpan snapshot peta gagal**. Ubah ketiganya bersamaan, dan pastikan `ensure...` runtime juga meng-`ALTER` kolom lama.

#### #7 — Token BRA/QR · KELIRU (klaim utama) · Berisiko

- Klaim "siapa pun bisa menyusun QR dari nomor BRA dan TWU" **keliru**. Token adalah HMAC-SHA256 dengan kunci `APP_KEY` ([BraModel.php:570-572](../models/BraModel.php#L570), [BraModel.php:581-583](../models/BraModel.php#L581)). Tanpa kunci rahasia itu, token tidak bisa dihitung.
- `APP_KEY` selalu terdefinisi ([config/app.php:155](../config/app.php#L155) melempar exception bila kunci tidak bisa dibuat). Jadi string fallback statis tidak pernah dipakai saat runtime.
- Benar: token legacy tidak mengikat isi naskah. Namun halaman verifikasi menampilkan isi dari DB, bukan dari kertas, sehingga pembaca tetap bisa membandingkan isinya.
- `md5` di dalam payload HMAC dan pemotongan ke 64-bit tidak memberi jalur serangan praktis.
- **Rekomendasi:** jangan ubah rumus (lihat bagian B). Opsional: tampilkan label "format lama" saat verifikasi lolos lewat jalur legacy.

#### #8 — Sandi reset `Kendari304` · SEBAGIAN · Hati-hati

- Benar: fallback literal ada di [config/app.php:380](../config/app.php#L380) dan [SettingsModel.php:30](../models/SettingsModel.php#L30), dan tidak tercantum di `.env.example`.
- Mitigasi yang tidak disebut laporan: akun yang masih memakai sandi bawaan wajib ganti sandi sebelum memakai aplikasi ([AuthHelper.php:52-57](../helpers/AuthHelper.php#L52), [AuthHelper.php:123-134](../helpers/AuthHelper.php#L123)). Celah tersisa adalah jeda antara reset dan login pertama pemilik akun. Dalam jeda itu, siapa pun yang tahu `Kendari304` bisa login lebih dulu.
- Keliru: "admin yang mengganti username gagal tersinkron". Kondisi `WHERE id = 'USR-ADMIN' OR ...` tetap cocok lewat `id`. Aplikasi ini memang dirancang dengan dua akun tetap ([UserModel.php:74-103](../models/UserModel.php#L74)), jadi tidak ada "operator tambahan".
- **Perbaikan minimal:** tambahkan `DEFAULT_RESET_PASSWORD=` ke `.env.example` dengan instruksi mengisi nilai khusus situs.
- **Jebakan:** saran "sandi acak sementara" akan melumpuhkan gerbang wajib-ganti-sandi, karena gerbang itu mendeteksi sandi bawaan lewat `password_verify(DEFAULT_RESET_PASSWORD, ...)`. Saran itu butuh kolom flag `must_change_password` baru.

#### #9 — CSRF · SEBAGIAN · Aman

- Benar: `setSession()` tidak mereset `csrf_token` ([AuthHelper.php:427-447](../helpers/AuthHelper.php#L427)). Risikonya rendah, karena ID sesi diregenerasi dan token hanya berguna bersama cookie sesi korban.
- Benar: token diterima lewat `$_GET` di [BaseApiController.php:85](../api/controllers/BaseApiController.php#L85) dan [AuthController.php:55](../controllers/AuthController.php#L55). UI tidak pernah memakainya: logout memakai form POST di [sidebar.php:199](../views/layout/sidebar.php#L199), dan JS mengirim token lewat header atau body. Jadi jalur `$_GET` **aman dihapus**.
- Selisih umur cookie (7 hari) dengan batas idle (2 jam) dan umur mutlak (12 jam) tidak berbahaya, karena server menegakkan batasnya sendiri.
- Rate limit AI per sesi: benar, tetapi untuk mengulangnya pengguna harus login ulang, dan login di-throttle. Risikonya rendah karena hanya bisa dilakukan orang dalam.
- **Perbaikan:**
  1. Hapus `?? ($_GET['csrf_token'] ?? null)` di kedua tempat.
  2. Tambahkan `unset($_SESSION['csrf_token']);` di `setSession()`.
  3. Efek samping kecil: tab lain yang masih terbuka saat login ulang perlu dimuat ulang.

#### #19 — `ini_parse_quantity()` · SEBAGIAN · Aman

- Benar: fungsi ini butuh PHP 8.2 ([RenopsFotoModel.php:56](../models/RenopsFotoModel.php#L56)), sedangkan README menulis PHP 8.0+.
- Konteks tambahan: kode sudah memakai `array_is_list()` (PHP 8.1+) di [RenopsDokumenService.php:384](../services/RenopsDokumenService.php#L384). Jadi fitur RENOPS memang tidak pernah berjalan di PHP 8.0. PHP lokal adalah 8.5.8.
- **Perbaikan paling murah dan aman:** ubah syarat di README ke **PHP 8.2+**. Polyfill tidak perlu, kecuali server produksi memang masih 8.0/8.1. Versi server produksi perlu dicek.
- Pesan "maksimal 5 MB" dibanding konstanta 5,5 MB ([RenopsFotoModel.php:10](../models/RenopsFotoModel.php#L10)) tampaknya toleransi yang disengaja. Ini kosmetik.

#### #20 — Path traversal `removeImageFile` · LATEN · Aman

- `image_path` selalu dibuat server dari nama acak ([DokumenPetaSnapshotModel.php:136-150](../models/DokumenPetaSnapshotModel.php#L136)). Nilai `..` hanya bisa masuk lewat restore SQL oleh admin atau akses DB langsung.
- **Perbaikan aman** sebagai pertahanan berlapis: tolak `..` dan null-byte di [DokumenPetaSnapshotModel.php:396-409](../models/DokumenPetaSnapshotModel.php#L396).

#### #21 — Transaksi bocor · BENAR · Aman

- Benar: [RenopsModel.php:274](../models/RenopsModel.php#L274) dan [LaporanModel.php:297](../models/LaporanModel.php#L297) mengembalikan `false` tanpa `rollBack()`. [LaporanModel.php:286](../models/LaporanModel.php#L286) memanggil `rollBack()` tanpa cek `inTransaction()`.
- Dampak terbatas pada satu request, karena koneksi PDO tidak persistent ([config/database.php](../config/database.php)) dan MySQL me-rollback otomatis saat koneksi ditutup.
- **Perbaikan aman:** tambahkan `$db->rollBack();` sebelum `return false;`, dan tambahkan cek `inTransaction()` di `create()`.

#### #22 — ID pasal BRA bergeser · SEBAGIAN · Aman

- Benar: pre-claim ID tidak ada ([BraModel.php:422-459](../models/BraModel.php#L422)), berbeda dengan [OperasiRelationService.php:30-42](../services/OperasiRelationService.php#L30).
- Keliru: klaim "menimpa baris lain". Hasil penelusuran: isi akhir tetap benar, hanya ID yang bergeser. `BraSection.id` tidak dirujuk tabel lain, dan token BRA meng-hash `kode` serta `isi`, bukan `id`.
- **Perbaikan aman** (konsistensi): salin blok pre-claim dari `OperasiRelationService`. Manfaatnya kecil.

### Prioritas 2 laporan asli

#### #10 — `getMessage()` dan operator `@` · SEBAGIAN · Berisiko bila menyeluruh

- Ada 24 titik `getMessage()` yang sampai ke UI atau JSON. Contohnya [OperasiController.php:78](../controllers/OperasiController.php#L78) dan [AdminController.php:204](../controllers/AdminController.php#L204).
- Hanya pengguna login yang melihat pesan ini. Handler global sudah menyamarkan pesan saat `APP_DEBUG` mati ([config/app.php:81](../config/app.php#L81)).
- Jumlah operator `@` sekitar 65, bukan 41.
- Cara aman: lihat bagian F.

#### #11 — CSP, escaping, `extract()` · SEBAGIAN · Hati-hati

- **Bug nyata dan aman diperbaiki:** escape ganda judul halaman. [OperasiController.php:535](../controllers/OperasiController.php#L535) memanggil `htmlspecialchars`, lalu [header.php:96](../views/layout/header.php#L96) memanggil `e()` lagi. Akibatnya `&` tampil sebagai `&amp;` di tab browser. Hapus `htmlspecialchars` di controller.
- `verify_invalid.php` tidak bisa dieksploitasi saat ini. [BraController.php:103](../controllers/BraController.php#L103) sudah meng-escape, `OperasiController` hanya mengirim angka atau string tetap ([OperasiController.php:273](../controllers/OperasiController.php#L273), [OperasiController.php:427](../controllers/OperasiController.php#L427)), dan fallback di [verify_invalid.php:15](../views/bra/verify_invalid.php#L15) juga di-escape.
- `${code}` di [bra_form.js:318](../assets/js/pages/bra_form.js#L318) berasal dari `textContent` badge kode tetap. Statusnya LATEN.
- `EXTR_OVERWRITE` → `EXTR_SKIP` (hati-hati): `EXTR_SKIP` tidak menimpa variabel lokal `render()` seperti `$layout` dan `$options`. Bila ada controller yang sengaja mengirim key bernama sama, perilakunya berubah. Telusuri dulu.
- CSP ketat butuh memindahkan banyak inline script dan `onclick`, jadi ini refaktor besar. Bila ingin mulai, gunakan `Content-Security-Policy-Report-Only` lebih dulu.

#### #12 — Host header · BENAR (konfigurasi) · Hati-hati

- Benar: saat `APP_URL` kosong, host apa pun yang lolos regex karakter diterima ([config/app.php:218-223](../config/app.php#L218)).
- Dampak terbatas: penyerang hanya memengaruhi respons untuk request miliknya sendiri. Tidak ada email reset sandi. URL QR BRA dibuat dari request pengguna yang menyimpan naskah. Risiko nyata hanya ada bila memakai reverse proxy yang men-cache halaman.
- **Jebakan:** mengisi `APP_URL` tunggal akan merusak akses bila kantor membuka aplikasi lewat beberapa alamat (IP LAN, domain, localhost). Redirect dan aset akan diarahkan ke satu host. Yang lebih aman adalah daftar `ALLOWED_HOSTS`: host yang tidak terdaftar jatuh ke host pertama.

#### #13 — DDL runtime · BENAR (fakta) · Berisiko

- Ada 10 berkas yang memuat DDL runtime, dan semuanya disengaja. Lihat bagian C.
- Penghematan yang aman: cache hasil `SHOW COLUMNS ... 'satuan_jarak'` di variabel `static` ([RenopsModel.php:179](../models/RenopsModel.php#L179), [RenopsModel.php:297](../models/RenopsModel.php#L297)). Manfaatnya kecil.

#### #14 — Duplikasi PDF service · BENAR · Hati-hati

- Benar: dua kelas hampir identik (133 dan 134 baris), dan `--no-sandbox` dipakai di [RenopsPdfService.php:103](../services/RenopsPdfService.php#L103) dan [BraPdfService.php:104](../services/BraPdfService.php#L104).
- `BraPdfService.php` sedang punya perubahan yang belum di-commit. Menggabungkan sekarang berisiko bentrok, dan kedua keluaran PDF harus diuji ulang.
- Masalah yang lebih relevan daripada duplikasi (dari audit sebelumnya): `exec()` Chrome tidak punya batas waktu ([RenopsPdfService.php:113](../services/RenopsPdfService.php#L113)). Chrome yang macet akan menahan worker PHP.

#### #15 — Paginasi · SEBAGIAN · Hati-hati

- **Jebakan:** dashboard menyusun daftar bulan dan statistik dari seluruh hasil `OperasiModel::getAll()` ([dashboard.php:16](../views/dashboard.php#L16), dengan perulangan di baris 34). Menambah `$limit` bawaan di model akan memotong statistik secara diam-diam.
- **Aman dan murah:** `getDbStats()` dipanggil di semua tab ([AdminController.php:231](../controllers/AdminController.php#L231)), padahal hanya dipakai di `views/admin/partials/tab_backup.php`. Panggil hanya saat `tab=backup`.
- Untuk skala satu kantor regional (ratusan operasi per tahun), ini belum jadi hambatan.

#### #16 — Arsitektur · BENAR (fakta) · Berisiko bila sekaligus

- Benar: tidak ada `composer.json`, ada 94 berkas tes ad-hoc, dan ukuran kelas sesuai laporan.
- Klaim "`OperasiController` memutasi `$_POST`" tidak ditemukan berupa penugasan langsung `$_POST[...] =`.
- Ini saran refaktor, bukan bug. Migrasi ke PSR-4 sekaligus berisiko regresi tinggi tanpa manfaat langsung bagi pengguna.
- Opsi berisiko rendah: jalankan PHPStan level rendah sebagai alat bantu, tanpa mengubah struktur kode.

#### #23 — Sidik sesi, panjang sandi, kolom sensitif · SEBAGIAN · Berisiko (bagian pertama)

- Urutan refresh sidik disengaja. Lihat bagian D.
- `updateUserProfile()` tanpa cek panjang di model: satu-satunya pemanggil ([AdminController.php:140](../controllers/AdminController.php#L140)) sudah mengecek minimal 8 karakter di baris 134. Menambah cek di model aman, tetapi manfaatnya kecil.
- `maps_api_key`, `reset_token`, `reset_token_expiry` memang ada di skema, tetapi tidak dipakai kode mana pun, sehingga nilainya `NULL`. Tidak ada yang bocor. Respons ini juga hanya untuk ADMIN, dan `unset` tambahan aman.

#### #24 — Penomoran BRA dan token RENOPS · SEBAGIAN · Berisiko (token)

- Klaim "tidak mengunci baris apa pun" sebagian keliru. Di isolation level bawaan MySQL (REPEATABLE READ), `LIKE '%yy' ... FOR UPDATE` ([BraHelper.php:181-183](../helpers/BraHelper.php#L181)) memindai seluruh tabel dan mengambil next-key lock pada semua baris yang dipindai. Akibatnya, insert yang berjalan bersamaan ikut terserialisasi.
- Ada pengaman tambahan: `UNIQUE KEY no_bra` ([0000_base_schema.sql:98](../database/migrations/0000_base_schema.sql#L98)) dan loop cek tabrakan. Kasus terburuk adalah error duplikat, bukan nomor ganda yang tersimpan.
- Dengan dua akun, peluang tabrakan sangat kecil. **Jangan ubah skema** untuk ini.
- Memperpanjang token: lihat bagian E.

### Prioritas 3 laporan asli

#### #17 — Google Fonts · BENAR · Aman

- Font Google hanya dimuat di [login.php:55-57](../views/auth/login.php#L55) dan [public_header.php:42-44](../views/layout/public_header.php#L42). Layout utama sudah offline.
- **Perbaikan aman:** bundel `.woff2` Plus Jakarta Sans dan JetBrains Mono ke `assets/fonts/`, yang saat ini baru berisi `inter`.

#### #18 — Kode mati dan sample Nginx · BENAR · Aman, dengan catatan

- `exportJsonBackup()` dan `restoreFromJson()` hanya dirujuk oleh `method_exists` di [test_phase4_refactoring_architecture.php:62](../tests/test_phase4_refactoring_architecture.php#L62) dan baris 64. Hapus juga kedua assertion itu.
- **Catatan rentang baris:** `snapshotBeforeRestore()` dimulai di [BackupService.php:286](../services/BackupService.php#L286) dan masih dipakai restore. Yang dihapus hanya `JSON_TABLE_MAP` (baris 29-61) dan blok baris 95-284. Jangan pakai rentang "100–290" dari laporan asli.
- `kategori` di [MasterController.php:87](../controllers/MasterController.php#L87) memang diabaikan oleh `createTim()` ([MasterModel.php:58-66](../models/MasterModel.php#L58)). Tidak berbahaya, cukup dibersihkan.
- Sample Nginx sudah memakai docroot `/public` ([nginx.conf.sample:14](../nginx.conf.sample#L14)), jadi aman secara default. Menambah blok `deny` hanya berguna bila server salah dikonfigurasi.

### Bagian "Perampingan" laporan asli

- Sekitar 190 baris backup JSON: **benar**, aman dihapus dengan catatan rentang baris di #18.
- Sekitar 120 baris PDF: benar, tetapi **tunda** karena ada perubahan yang belum di-commit dan butuh uji ulang dua keluaran PDF.
- Memindahkan `SHOW COLUMNS` dan `ALTER` ke migrasi: **jangan**. Lihat bagian C.

### Ketidaktepatan angka di laporan asli

- Operator `@`: sekitar 65, bukan 41.
- Baris JavaScript di luar vendor: 24.227 di working tree saat ini, bukan 19.906.
- Cache tile: punya TTL 7 hari, tidak "permanen tanpa TTL".

---

## Hal penting yang tidak ada di laporan asli

Hal berikut berasal dari audit keamanan sebelumnya (berkas `AUDIT-HASIL-LENGKAP.md` di folder `security-audit-skill/QR-SAR/run-1`). Statusnya perlu validasi.

1. **Hapus ikon dengan wildcard `LIKE`** ([GisApiController.php:689-690](../api/controllers/GisApiController.php#L689)). Nama berkas `.` atau `_` bisa mereset ikon di semua titik peta. Dijelaskan di #4.
2. **`exec()` Chrome tanpa batas waktu** ([RenopsPdfService.php:113](../services/RenopsPdfService.php#L113)). Proses yang macet menahan worker PHP.
3. **Dump SQL memuat hash sandi** semua akun ([BackupService.php:314-366](../services/BackupService.php#L314)). Berkas cadangan harus diperlakukan sebagai data rahasia.
4. **Ekspor SQL diproses lewat GET sebelum cek CSRF** (`AdminController.php`, sekitar baris 26-38). Dampaknya terbatas, karena berkas terunduh ke browser admin sendiri.

---

## Urutan pengerjaan yang disarankan

**Gelombang 1 — aman, kecil, dan langsung bermanfaat**

1. #1 `trim()` sandi baru di `changePassword()`, plus satu tes.
2. #21 `rollBack()` sebelum `return false`.
3. #4 nama berkas hapus ikon dibatasi ke format upload dan URL dicocokkan persis, serta header CSP di `apache.conf.sample`.
4. #9 hapus CSRF via `$_GET` dan reset token saat login.
5. #5 `follow_location=0`, cek gambar, https saja.
6. #20 tolak `..` di `removeImageFile()`.
7. #11 hapus escape ganda judul halaman.
8. #19 ubah syarat README ke PHP 8.2+.

**Gelombang 2 — perlu sedikit desain**

- #2 ketatkan DROP/TRUNCATE dan tambah lock file.
- #8 isi `DEFAULT_RESET_PASSWORD` per situs.
- #10 sembunyikan pesan hanya untuk `PDOException`.
- #6 migrasi `captured_by` di tiga tempat.
- #15 muat `getDbStats()` hanya di tab backup.
- #18 hapus kode backup JSON berikut assertion tesnya.
- #17 bundel font lokal.

**Jangan dikerjakan apa adanya**

- #3, #7, #13, saran pertama #23, perpanjangan token di #24, masking menyeluruh di #10, dan `APP_URL` tunggal di #12 bila aplikasi diakses lewat beberapa alamat.

## Yang belum diverifikasi

- Tidak ada kode yang dijalankan. Semua kesimpulan berasal dari membaca kode.
- Versi PHP dan MySQL di server produksi tidak diketahui. Ini memengaruhi #19 (8.2+) dan #24 (isolation level).
- Konfigurasi server produksi tidak diketahui (`AllowOverride`, `mod_headers`, reverse proxy). Ini memengaruhi #4 dan #12.
