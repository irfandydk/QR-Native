# Desain: Kategori Alut & Palsar (ALUT Darat/Laut/Udara, PALSAR)

Tanggal: 2026-11-07
Status: menunggu persetujuan

## 1. Maksud & alasan

Data "Alut dan Palsar" saat ini hanya berupa nama, satuan, dan jumlah. Setiap keluaran menebak jenisnya dari nama:
- **Dokumen RENOPS** memakai kata kunci di `RenopsDokumenService::splitAlut/grupAset` untuk memisahkan Sarana dan Peralatan, serta mengelompokkan Aset Darat/Laut/Udara.
- **BRA SAR** memakai `isTransportAlut()` di `assets/js/bra/bra_templates.js` untuk kalimat "… menggunakan …".
- **Naskah WA dan BRA** mencampur semua item tanpa urutan.

Tebakan ini meleset. Contohnya, "Drone Thermal" dan "Aqua Eye" tidak tertangkap, dan "KN SAR Pacitan" tidak dikenali sebagai kapal.

Tujuannya: setiap item alut punya **kategori pasti** yang dipilih manusia dan ikut tersimpan di setiap laporan. Dengan begitu Laporan (Awal dan LAPBANG), BRA SAR, dan Dokumen RENOPS membedakan Alut dan Palsar secara akurat.

## 2. Keputusan pengguna

| # | Keputusan |
|---|-----------|
| U1 | Ada 4 kategori: **ALUT Darat**, **ALUT Laut**, **ALUT Udara**, dan **PALSAR** (Palsar tidak dipecah). |
| U2 | Pendekatan **A**: kategori disimpan di master **dan** ikut tersalin ke setiap item di setiap laporan. |
| U3 | Naskah WhatsApp (dan BRA SAR) tetap memakai **satu judul gabungan** "ALUT DAN PALSAR". |
| U4 | Kalimat BRA "diberangkatkan … menggunakan …" hanya menyebut **kendaraan**: ALUT kecuali drone. |

Usulan yang tidak dibantah pengguna dan dipakai sebagai keputusan:
- Ke-16 item master diisi kategori awal otomatis (lihat §5), lalu bisa diperiksa dan diubah di Master Data.
- Item yang diketik bebas di form mendapat kategori dari pilihan di form. Item dari master membawa kategorinya.
- Laporan lama tanpa kategori dicocokkan lewat nama ke master, lalu memakai tebakan kata kunci bila tidak ditemukan.
- RENOPS: Sarana = semua ALUT, Peralatan = PALSAR, dan tabel Aset dikelompokkan sesuai kategori.
- BRA: kalimat "diberangkatkan … menggunakan …" hanya menyebut item ALUT.
- Di dalam judul gabungan, item diurutkan ALUT Darat → Laut → Udara → PALSAR.

## 3. Tujuan, non-tujuan, anti-tujuan

**Tujuan**
- Kategori tersedia sebagai data di master, di Laporan Awal (`DetailAlut`), dan di JSON `detail_alut` RENOPS dan LAPBANG.
- Semua keluaran memakai kategori itu, bukan tebakan nama.
- Data lama tetap tampil benar lewat cadangan pencocokan.

**Non-tujuan**
- Tidak mengubah daftar satuan, jumlah, maupun unsur SAR.
- Tidak memisahkan Alut dan Palsar ke tabel master yang berbeda.
- Tidak mengubah dokumen RENOPS yang **sudah terbit**, karena isinya beku.

**Anti-tujuan** (dianggap gagal walau secara teknis jalan)
- Mengubah kategori di master diam-diam mengubah laporan lama atau dokumen terbit.
- Item tanpa kategori membuat form, WA, BRA, atau dokumen error.
- Operator dipaksa mengisi ulang data lama.

## 4. Pendekatan (dipilih: A)

| | Pendekatan | Trade-off | Hasil |
|---|---|---|---|
| A | Kolom `kategori` di `MasterAlut` **dan** di setiap item laporan (`DetailAlut.kategori`, kunci `kategori` di JSON) | Akurat untuk item ketik-bebas dan tahan perubahan master. Perlu 1 migrasi dan pilihan kategori di 3 form. | **Dipilih** |
| B | Kolom hanya di master, kategori dicari saat dibaca | Perubahan kecil, tetapi item ketik-bebas tak pernah punya kategori dan mengubah master mengubah laporan lama | Ditolak |
| C | Tabel master terpisah untuk Alut dan Palsar | Memecah semua form dan alur data tanpa manfaat tambahan | Dibuang |

## 5. Rancangan

### 5.1 Nilai kategori
- Kode tersimpan: `ALUT_DARAT`, `ALUT_LAUT`, `ALUT_UDARA`, `PALSAR`.
- Label tampilan: "ALUT Darat", "ALUT Laut", "ALUT Udara", "PALSAR".
- Urutan baku: Darat, Laut, Udara, PALSAR.
- Nilai lain atau kosong diperlakukan "belum ada" dan diselesaikan oleh resolver (§5.3).
- Satu sumber kebenaran di PHP: kelas helper `AlutKategori` di `helpers/`, berisi konstanta, label, urutan, `normalize()`, `resolve()`, dan `sort()`.
- JS memakai daftar yang sama, disuntik dari server sebagai konfigurasi halaman. Daftar tidak ditulis ulang di setiap file JS.

### 5.2 Skema (1 migrasi: `database/migrations/2026_11_07_add_kategori_alut.sql`)
- `MasterAlut.kategori VARCHAR(20) NOT NULL DEFAULT 'PALSAR'`.
- `DetailAlut.kategori VARCHAR(20) NULL`. NULL = data lama, diselesaikan resolver.
- Mengisi master yang sudah ada dengan `UPDATE` bertarget nama (idempoten):
  - ALUT_DARAT: Rescue Car, Rescue Carrier.
  - ALUT_LAUT: Rescue Boat 210, Rescue Boat 307, Rigid Inflatable Boat (RIB), Rubber Boat, Longboat Masyarakat, KN SAR Pacitan.
  - ALUT_UDARA: Drone Thermal.
  - PALSAR: Aqua Eye, Palsar Medis/Evakuasi/Komunikasi/Selam/Mounteneering, Kantong Jenazah.
  - Item master lain yang tidak terdaftar diisi dengan tebakan kata kunci yang sama dengan resolver, sebagai pengisian awal yang bisa dikoreksi.
- **Pengisian sekali saat migrasi (keputusan builder-check, agar sesuai anti-tujuan):** semua `DetailAlut` lama dan setiap item JSON `detail_alut` di `Renops` dan `LaporanHarian` diisi `kategori` hasil `AlutKategori::resolve` **satu kali**.
  - Langkah ini dijalankan sebagai langkah PHP di `database/migrate.php`, idempoten: hanya item yang belum punya kategori.
  - Setelah itu kategori laporan lama beku. Mengubah kategori di master **tidak** mengubah laporan yang sudah tersimpan.
  - Resolver saat dibaca tetap ada sebagai cadangan untuk data yang lolos, misalnya restore cadangan lama.

### 5.3 Resolver kategori (`AlutKategori::resolve(array $item): string`)
Urutan resolusi:
1. `kategori` milik item, bila valid.
2. Kategori master berdasarkan `id_alut` (bila > 0).
3. Kategori master berdasarkan nama (case-insensitive, setelah trim).
4. Tebakan kata kunci kata utuh, memakai daftar yang kini ada di `RenopsDokumenService`:
   - **Laut:** kapal, rib, perahu, boat, speed, speedboat, longboat.
   - **Udara:** heli, helikopter, pesawat, drone.
   - **Darat:** car, truck, truk, mobil, motor, ambulans.
   - Selain itu PALSAR.

Langkah 4 sengaja menempatkan drone sebagai ALUT Udara, sesuai keputusan U1 dan master.

Peta master `nama/id → kategori` dimuat sekali per request (cache statis).

### 5.4 Penyimpanan di laporan
- **Laporan Awal:** `OperasiModel` dan `OperasiRelationService::applyDifferentialAlut` menulis `DetailAlut.kategori` dari input. Bila kosong, nilainya diisi `AlutKategori::resolve`.
- **RENOPS & LAPBANG:** setiap item JSON `detail_alut` menyimpan `kategori`. Model menormalkan item saat simpan, dengan kosong diisi resolver. Ini termasuk pewarisan dari laporan pendahulu (`HierarchyHelper`).
- **Master Data:** buat dan ubah item mewajibkan kategori lewat select. `MasterModel::createAlut/updateAlut`, API master, dan tampilan tab Alut menampilkan kolom kategori.

### 5.5 Form (Laporan Awal, RENOPS, LAPBANG)
- Baris input alut mendapat select kategori di samping satuan.
- Memilih item dari master mengisi kategori otomatis (seperti satuan sekarang).
- Mengetik nama bebas mengisi tebakan resolver, yang bisa diubah sebelum ditambahkan.
- Setiap item di daftar menampilkan dan mengizinkan ubah kategori, lewat pola yang sama dengan `updateAlutItem(idx, 'satuan', …)`.
- Teks ringkas `alut_text`/`alut_palsar` (kolom teks lama) dibentuk dari daftar yang **sudah diurutkan per kategori**.

### 5.6 Keluaran
- **Naskah WA** (Laporan Awal, RENOPS, LAPBANG, Penutupan) dan **BRA SAR**: judul tetap "ALUT DAN PALSAR" (U3), dengan item diurutkan sesuai §5.1. Format setiap baris tidak berubah.
- **BRA SAR** (`bra_templates.js`): `isTransportAlut` diganti pengecekan `kategori` diawali `ALUT_`, **kecuali drone** (keputusan pengguna U4: hanya kendaraan, karena drone bukan alat angkut tim). Pengecualian dicek dengan kata utuh "drone" pada nama. Item JSON tanpa kategori diselesaikan server sebelum dikirim ke JS. `tests/js/bra_templates.test.js` disesuaikan.
- **Dokumen RENOPS** (`RenopsDokumenService`):
  - `splitAlut`: Sarana = kategori `ALUT_*`, Peralatan = `PALSAR`.
  - `grupAset`: grup dari kategori (Darat, Laut, Udara).
  - Daftar kata kunci pindah ke `AlutKategori` (dipakai hanya sebagai cadangan).
  - Kontrak data dokumen tidak berubah.
  - Karena `source_hash` mencakup sections, dokumen terbit yang hasil resolusinya kini berbeda akan berlabel "ada perubahan belum diterbitkan". Ini memang perilaku yang benar.
- **Cetak Laporan Awal & LAPBANG** (`laporan_awal_print.php`, `sitrep_print.php`) dan tab Kekuatan: daftar alut diurutkan per kategori, dan setiap item diberi label kategori kecil.

### 5.7 Keputusan builder-check (saya ambil)
- **Kolom teks lama** `alut_text`/`alut_palsar`: hanya dibentuk ulang (urut per kategori) saat laporan **disimpan**. Laporan lama yang tidak disimpan ulang tetap memakai teks lamanya.
- **Master Data:** kategori boleh diubah kapan saja, dan item boleh diganti nama atau dihapus seperti sekarang. Karena laporan lama sudah beku (§5.2), perubahan master hanya memengaruhi pilihan berikutnya.
- **Dokumen RENOPS yang sudah terbit** tidak berubah, karena isinya beku. Setelah fitur ini aktif, dokumen yang Sarana/Peralatannya kini terbaca berbeda (mis. Drone Thermal menjadi Sarana) akan berlabel "ada perubahan belum diterbitkan". Itu benar adanya: terbitkan ulang bila ingin memakai kategori baru. Tidak ada penyesuaian hash diam-diam.
- **Form:** kategori hasil tebakan untuk nama yang diketik bebas langsung tampil di select dan bisa diubah. Tidak ada peringatan tambahan.

## 6. Kendala
- PHP 8.5, MySQL, tanpa framework. Ikuti pola Model statis, helper, dan test `tests/test_*.php` dengan `DB_DATABASE=qrsar_test`.
- Tiga form JS (`operasi_form.js`, `renops_form.js`, `sitrep_form.js`) punya UI daftar alut yang hampir sama. Ubah ketiganya dengan pola yang sama; jangan refactor besar.
- Jangan commit. Working tree `main` berisi WIP pengguna.
- Escape semua keluaran.

## 7. Diserahkan ke builder
- Letak persis select kategori di setiap form dan gaya labelnya.
- Cara menyuntik daftar kategori ke JS: `window` config atau atribut data, mengikuti pola halaman masing-masing.
- Bentuk label kategori di cetak dan tab Kekuatan.
- Pembagian task dan urutan test.
