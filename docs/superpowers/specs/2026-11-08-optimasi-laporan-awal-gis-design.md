# Desain: Optimasi Form Laporan Awal, Naskah WA, dan Halaman Plotting Peta (GIS)

Tanggal: 2026-11-08 · Status: menunggu persetujuan

## 1. Maksud & alasan

Operator mengisi Laporan Awal, menyusun naskah WA, lalu memplot peta dan mengimpornya ke RENOPS di bawah tekanan waktu. Saat ini beberapa hal memperlambat atau membingungkan:
- isian bebas yang seharusnya pilihan;
- urutan daftar yang hanya bisa digeser satu per satu;
- naskah WA yang tidak menyebut asal radial dan menulis "0 orang";
- daftar titik GIS yang panjang dan bercampur;
- alat peta yang mudah ter-reset atau kurang fleksibel;
- penamaan objek peta yang sering lupa diisi, sehingga impor ke RENOPS jadi tebak-tebakan.

Tujuannya: input lebih cepat, naskah lebih tepat, dan plotting → RENOPS mengalir tanpa operator harus mengingat nama.

## 2. Tujuan, non-tujuan, anti-tujuan

**Tujuan**: 15 butir dari permintaan pengguna (bagian 4), ditambah dua rekomendasi pengguna (penamaan otomatis & impor per SRU).

**Non-tujuan**:
- Drag-reorder di form RENOPS/LAPBANG. Yang diminta hanya form Laporan Awal.
- Mengubah kalimat BRA "MENGGUNAKAN … DENGAN JARAK … RADIAL". Yang diminta hanya naskah WA.
- Mengubah format naskah WA RENOPS.
- Perubahan pola E-SAR. Pola ini sudah punya arah.

**Anti-tujuan**:
- Data lama rusak, misalnya `jenis_kejadian` di luar daftar hilang saat diedit.
- Urutan alut di form berbeda dari urutan di naskah WA.
- Nama yang diketik operator ditimpa nama otomatis.
- Impor RENOPS menghapus baris SRU yang diisi manual.

## 3. Batasan

- PHP 8.5 statis + MySQL; migrasi lewat `database/migrate.php` (berkas SQL bernomor tanggal) atau `ensureSchemaUpdated` model GIS (pola yang sudah dipakai `GisModel`).
- Aset JS lokal di `assets/vendor` (aplikasi harus jalan offline). Tidak boleh memuat CDN.
- Uji: `DB_DATABASE=qrsar_test php tests/run_all.php` + uji JS `node tests/js/*.test.js`; TDD (uji gagal dulu).
- Leaflet sudah dipakai di GIS; Tailwind di-build ulang lewat perintah di README bila menambah kelas baru.

## 4. Rincian per butir

### A. Form Laporan Awal (`views/operasi/form.php`, `assets/js/pages/operasi_form.js`)

**A1. Jenis Kejadian = dropdown.**
- Ganti `<input list>` dengan `<select>` berisi 5 opsi yang sudah ada di `datalist`, plus "Lainnya…".
- Bila "Lainnya…" dipilih, muncul input teks; nilai yang terkirim tetap satu field `jenis_kejadian`.
- Saat edit: bila nilai tersimpan tidak ada di daftar, select menunjuk "Lainnya…" dan input berisi nilai lama (anti-tujuan data hilang).
- Normalisasi server (`OperasiModel::normalizeJenisKejadian`) tidak berubah.

**A2. Unsur SAR bawaan = 1 baris.**
- Default `[['SMC',1],['Staf',1]]` (form.php:85-86) diganti `[['nama_unsur' => 'SMC dan Staf', 'jumlah_personil' => 2]]`.
- *Keputusan saya*: jumlah personil 2 (gabungan dua baris lama).
- Data operasi lama tidak diubah.

**A3. Drag-reorder Unsur & Alut.**
- Pustaka: **SortableJS** versi tetap, disalin ke `assets/vendor/sortablejs/Sortable.min.js` (mendukung sentuh).
- Tiap baris mendapat pegangan drag (ikon ⋮⋮). Tombol ↑↓ dihapus, digantikan drag. Tombol hapus tetap ada.
- **Unsur**: satu daftar, drag bebas.
- **Alut**: daftar dirender per kelompok kategori (judul kecil: ALUT Darat / ALUT Laut / ALUT Udara / PALSAR, urutan `AlutKategori::URUTAN`).
  - Drag hanya di dalam kelompoknya (satu instance Sortable per kelompok, tanpa `group` bersama).
  - Pindah kategori lewat select kategori di baris; baris otomatis berpindah ke kelompok tujuan, di posisi akhir.
- Urutan simpan = urutan tampil (kelompok berurutan, lalu urutan di dalam kelompok). Dengan begitu `AlutKategori::sort` (stabil) di naskah WA menghasilkan urutan yang sama persis.

### B. Naskah WA (`services/WhatsAppTemplateService.php`)

**B1. Radial disertai asal pemberangkatan.**
- Kolom baru `Operasi.asal_berangkat VARCHAR(191) NULL` (migrasi SQL baru).
- Nilainya diisi dari pilihan "Pangkalan / Pos Awal Pemberangkatan" (`origin_name`, sudah dikirim form). `OperasiModel::create/update` menyimpannya.
- Saat edit, `origin_base_select` memilih opsi yang namanya sama dengan `asal_berangkat`. Bila tidak ada yang cocok, dipakai default seperti sekarang.
- Format naskah: `jarak tempuh : 12 NM (Radial 277° dari Kantor SAR Kendari)`.
  - Tanpa radial → `12 NM (dari Kantor SAR Kendari)`.
  - Tanpa asal → format lama.
  - Tanpa jarak → `-`, seperti sekarang.

**B2. Korban 0 → "-"** di naskah Laporan Awal, LAPBANG, dan Penutupan:
- Header "Data Korban: 0 orang" → "Data Korban: -".
- Setiap baris status S/MD/DP/H yang 0 → "• S : -".
- Nilai > 0 tetap "N orang" (Laporan Awal) atau "N ORANG" (LAPBANG).
- Satu helper format, dipakai kedua generator.

### C. Halaman Plotting Peta (`views/gis.php`, `assets/js/gis_master.js`, `assets/js/gis/*.js`, `models/GisModel.php`)

**C1. Daftar titik per kategori, bisa dibuka/tutup, kartu ringkas.**
- Kelompok, dalam urutan tampil:
  1. **Pangkalan SAR**: Kantor SAR, POS SAR, Unit Siaga SAR.
  2. Dermaga SAR.
  3. LKP.
  4. Penemuan Korban: "Penemuan Korban" dan "Lokasi Penemuan Korban".
  5. Posko.
  6. Entry/Exit Poin.
  7. Tanpa Kategori.
- *Keputusan saya*: Dermaga SAR tidak digabung ke Pangkalan.
- Header kelompok berisi nama, jumlah, dan tombol buka/tutup. Status buka/tutup diingat per browser (`localStorage`, try/catch). Bawaan: Pangkalan SAR tertutup, lainnya terbuka.
- Kartu ringkas: satu baris, berisi ikon kecil, nama, koordinat DMS ringkas, dan tombol ikon (salin, sembunyikan, edit, hapus). Info jarak/arah dari Kansar dan badge operasi dipindah ke tooltip `title`.

**C2. Search area persegi bisa di-drag utuh.**
- Saat menyunting persegi, tampil pin tengah (seperti lingkaran) selain pin sudut.
- Menggeser pin tengah menggeser seluruh titik sudut dengan selisih yang sama; ukuran dan bentuk sunting manual tetap terjaga.
- `centerLat/centerLng` diperbarui.

**C3. Jarak lurus tidak ter-reset saat klik.**
- Mode lurus: setelah titik A dan B ada, klik peta diabaikan.
- Status kartu menampilkan "Garis lengkap — tekan Reset untuk membuat ulang".
- Tombol **Reset Garis** baru mengosongkan titik (setara `clearMeasureVisual` + kosongkan `measurePoints`) tanpa menutup kartu.
- Titik A/B tetap bisa digeser (drag marker sudah ada).
- Mode jalur (banyak titik) tidak berubah: klik tetap menambah titik.

**C4. Flip pola Parallel Track & Creeping Line.**
- Dua tombol toggle: **Flip ⇆** (cermin timur-barat) dan **Flip ⇅** (cermin utara-selatan). Kombinasinya memberi 4 kemungkinan titik awal.
- Algoritma: cerminkan titik poligon terhadap sumbu tengah bbox → jalankan generator yang ada → cerminkan waypoint hasilnya kembali. Pola tetap di dalam poligon untuk bentuk apa pun.
- Kolom baru `GisSearchPattern.flipH TINYINT DEFAULT 0`, `flipV TINYINT DEFAULT 0` agar saat edit toggle-nya kembali.
- E-SAR tidak berubah.

**C5. Warna & transparansi garis dan isi terpisah.**
- Editor search area punya dua baris swatch: **Warna Garis** dan **Warna Isi**. Memilih warna garis tidak lagi mengubah warna isi.
- Slider **Transparansi Garis** baru ditambahkan; slider transparansi isi sudah ada.
- Kolom baru `GisPolygon.strokeOpacity DOUBLE DEFAULT 1`.
- Semua render poligon (peta, daftar, capture 16:9) memakai `opacity: strokeOpacity`.

**C6. Peta blank saat di-zoom.**
- *Dugaan*: citra Google satelit/hybrid diminta sampai `maxNativeZoom: 20`, padahal di wilayah Sultra citra hanya tersedia sampai ±18. Tile kosong lalu tampil blank.
- Wajib dibuktikan dulu di browser: zoom bertahap, periksa permintaan tile, lalu cari zoom terakhir yang berisi gambar.
- Perbaikan: turunkan `maxNativeZoom` layer terkait ke zoom terakhir yang terbukti ada, agar Leaflet memperbesar tile itu. `maxZoom` 22 tetap.
- Bila dugaan salah, cari akar masalahnya dulu sebelum mengubah apa pun.

**C7. Penamaan otomatis.** Nama hanya diisi otomatis bila kolom nama kosong; nama yang diketik operator tidak ditimpa.

| Objek | Nama otomatis | Catatan |
|---|---|---|
| Search area | `SRU N` | N = nomor terkecil yang belum dipakai area operasi ini |
| Pola | `Pola Pencarian Area {nama area} ({PS/CS/E-SAR})` | Mengikuti area target; diperbarui bila area target diganti dan nama masih otomatis |
| Rute/jalur | `Rute ke {nama area}`, atau `Rute {nomor}` bila tanpa area | Editor rute dapat select **Area Tujuan** (opsional); kolom baru `GisRoute.polygonId VARCHAR(191) NULL` |
| Titik | `{Kategori} {N}` (mis. `Penemuan Korban 1`); LKP cukup `LKP` | Hanya bila label kosong |

- Sebuah nama dianggap "otomatis" selama sama persis dengan hasil aturan di atas. Tidak perlu kolom tambahan.

**C8. Impor per SRU di form RENOPS (`assets/js/pages/renops_form.js`).**
- Tombol utama modal impor jadi **"Impor per SRU"**: satu baris SRU untuk setiap search area.
  - Nama SRU = nama area (`SRU 1`), tanpa "SRU 1 - SEARCH AREA SRU 1" seperti sekarang.
  - Diisi: koordinat batas, luas, pola dari pola yang terkait `polygonId`, dan rute dari rute yang terkait `polygonId`. Ringkasan rute (panjang, kecepatan) ditambahkan ke tugas.
  - **Menggabung, bukan mengganti**: baris SRU yang namanya sama dengan nama area diperbarui; area baru ditambahkan; baris SRU manual lain dibiarkan.
  - Pencocokan nama tidak peka huruf besar/kecil dan spasi di ujung. Format hasil impor lama `SRU n - {nama area}` juga dianggap cocok, agar tidak terjadi duplikat.
- Di atas tombol tampil **daftar periksa**, satu baris per area: ✓/✗ pola, ✓/✗ rute. Operator langsung tahu apa yang belum diplot sebelum mengimpor.
- Impor per objek (tab area/pola/jalur/titik) tetap ada seperti sekarang.

## 5. Pendekatan (yang dipertimbangkan & dipilih)

| Hal | Pilihan | Dipilih | Alasan |
|---|---|---|---|
| Drag-reorder | SortableJS lokal · Drag & drop bawaan browser | **SortableJS lokal** | Mendukung sentuh (tablet), teruji; bawaan browser tak bisa sentuh tanpa kode sendiri ±80 baris |
| Urutan alut | Bebas · Dalam kategori saja | **Dalam kategori** (keputusan pengguna) | Naskah WA diurutkan per kategori; form harus sama dengan naskah |
| Asal radial | Kolom `asal_berangkat` · Titik awal rute GIS | **Kolom baru** | Konsisten dengan pangkalan yang dipakai menghitung radial; rute bisa digambar dari titik lain |
| Penamaan & impor | Otomatis + keterkaitan · Saran nama saja | **Otomatis + keterkaitan** | Menghapus kebingungan operator; impor tidak bergantung ketikan |

## 6. Yang diserahkan ke pelaksana

- Ikon pegangan drag, tata letak header kelompok, ukuran pasti kartu ringkas (ikuti gaya Tailwind yang ada).
- Teks status/tooltip yang tepat.
- Struktur kode helper (mis. satu fungsi `formatJumlahKorban`), selama perilakunya seperti di atas.
- Urutan pengerjaan yang disarankan: A+B (form & naskah) → C1–C6 (GIS) → C7–C8 (penamaan & impor).

## 7. Verifikasi

- Uji PHP untuk:
  - A1: render select/Lainnya.
  - A2: default unsur.
  - B1: format naskah dengan dan tanpa radial/asal, serta simpan/baca `asal_berangkat`.
  - B2: semua generator.
  - C4/C5/C7: kolom & simpan model.
  - C8: logika gabung, sebaiknya sebagai fungsi murni yang bisa diuji di Node.
- Uji JS untuk:
  - pencerminan pola (C4), yaitu pola hasil flip tetap di dalam poligon;
  - aturan nama otomatis (C7);
  - pengelompokan titik (C1);
  - logika gabung impor (C8).
- Cek browser:
  - drag di form, termasuk emulasi sentuh;
  - kelompok titik & kartu;
  - drag persegi;
  - jarak lurus + Reset;
  - flip;
  - warna garis/isi;
  - zoom tanpa blank;
  - impor per SRU dengan daftar periksa.
