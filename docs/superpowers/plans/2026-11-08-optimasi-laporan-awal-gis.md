# Optimasi Laporan Awal, Naskah WA & Plotting GIS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Form Laporan Awal lebih cepat diisi, naskah WA lebih tepat, plotting GIS lebih rapi/fleksibel, dan impor plotting ke RENOPS per SRU tanpa operator mengingat nama.

**Architecture:** Perubahan server minimal (kolom baru lewat `ensureSchemaUpdated`, helper format di `WhatsAppTemplateService`, helper pilihan di `OperasiModel`). Logika baru di sisi JS ditaruh di modul murni yang bisa diuji Node:
- `assets/js/gis/naming.js`: penamaan otomatis dan pengelompokan titik.
- `assets/js/gis/patterns.js`: flip pola.
- `assets/js/renops_import.js`: daftar periksa dan penggabungan SRU.

Halaman (`gis_master.js`, `operasi_form.js`, `renops_form.js`) hanya merangkai modul tersebut ke UI. Drag-reorder memakai SortableJS yang disimpan lokal.

**Tech Stack:** PHP 8.5, MySQL, vanilla JS, Leaflet, SortableJS 1.15.6 (lokal), Tailwind 3.4.17 (build CLI).

**Spec:** `docs/superpowers/specs/2026-11-08-optimasi-laporan-awal-gis-design.md`

## Global Constraints

- Tidak ada commit (pengguna yang commit). Bekerja langsung di `main`.
- Uji PHP: `DB_DATABASE=qrsar_test php tests/<file>.php`. Suite: `DB_DATABASE=qrsar_test php tests/run_all.php`. Uji JS: `node tests/js/<file>.test.js`.
- TDD: tulis uji gagal dulu, lalu kode.
- Aset JS lokal di `assets/vendor/...`, dimuat lewat `assetUrl(...)`; tidak ada CDN saat runtime.
- Modul JS murni memakai pola `(function (global) { ... if (typeof module !== 'undefined' && module.exports) module.exports = X; })(typeof window !== 'undefined' ? window : globalThis);`, seperti `assets/js/bra/bra_templates.js`.
- Nama yang diketik operator tidak pernah ditimpa nama otomatis.
- Bila menambah kelas Tailwind baru, rebuild: `npx tailwindcss@3.4.17 -c tailwind.config.js -i assets/css/tailwind.src.css -o assets/css/tailwind.css --minify`.
- Bahasa UI: Indonesia.

## Review Focus

1. Jenis Kejadian lama di luar daftar (mis. "Orang Tenggelam") tetap utuh saat form diedit lalu disimpan tanpa diubah → uji di Task 2.
2. Restore backup sebelum fitur (tanpa kolom baru) lalu simpan operasi/poligon/pola/rute tetap jalan → uji di Task 1.
3. Alut diganti kategorinya lalu disimpan: urutan di form = urutan di naskah WA → uji di Task 3.
4. "Impor per SRU" dijalankan dua kali, atau ada SRU manual / SRU format lama `SRU 1 - SEARCH AREA SRU 1` → tidak ada duplikat, baris manual utuh → uji di Task 11.
5. Flip pada poligon cekung/tak beraturan → semua waypoint tetap di dalam poligon → uji di Task 8.

---

### Task 1: Skema kolom baru, persistensi model GIS, pulih setelah restore

**Files:**
- Modify:
  - `models/OperasiModel.php`: `$opsCols` di `ensureSchemaUpdated`, INSERT di `create`, UPDATE di `update`.
  - `models/GisModel.php`: `ensureSchemaUpdated`, `savePolygon`, `saveRoute`, `saveSearchPattern`, serta getter polygon/pattern (cast tipe).
  - `services/BackupService.php`: `restoreFromJson`, `restoreFromSQL`.
- Test: `tests/test_optimasi_skema.php`

**Interfaces:**
- Produces:
  - Kolom `Operasi.asal_berangkat VARCHAR(191) NULL` (AFTER `radial`).
  - Kolom `GisPolygon.strokeOpacity DOUBLE NOT NULL DEFAULT 1`.
  - Kolom `GisSearchPattern.flipH TINYINT(1) NOT NULL DEFAULT 0` dan `flipV TINYINT(1) NOT NULL DEFAULT 0`.
  - Kolom `GisRoute.polygonId VARCHAR(191) NULL`.
  - Payload simpan menerima kunci yang sama. Getter mengembalikan `strokeOpacity` (float), `flipH`/`flipV` (bool), `polygonId` (string|null).
  - `OperasiModel::create/update` menyimpan `asal_berangkat` dari `$data['asal_berangkat'] ?? $data['origin_name']`. Nilai di-trim, maks 191, '' → null. Pada `update`, bila kedua kunci tidak ada, nilai lama tetap.

- [ ] **Step 1: Tulis uji gagal** `tests/test_optimasi_skema.php`:
  - `SHOW COLUMNS` memuat keempat kolom.
  - `OperasiModel::create([...'origin_name' => '  Kantor SAR Kendari '])`, lalu `getById` → `asal_berangkat === 'Kantor SAR Kendari'`.
  - `update` tanpa kunci asal → nilai tetap.
  - `GisModel::savePolygon([... 'strokeOpacity' => 0.4])` → dibaca kembali `0.4`.
  - `saveSearchPattern([... 'flipH' => true, 'flipV' => false])` → dibaca `flipH === true`, `flipV === false`.
  - `saveRoute([... 'polygonId' => 'poly-x'])` → dibaca `'poly-x'`.
  - Review Focus 2:
    - `ALTER TABLE GisPolygon DROP COLUMN strokeOpacity`, lalu jalankan subproses `php -r` yang memanggil `GisModel::ensureSchemaUpdated(true)` → kolom kembali.
    - `BackupService.php` berisi `GisModel::ensureSchemaUpdated(true)` dan `OperasiModel::ensureSchemaUpdated(true)` di kedua jalur restore.
    - `finally` mengembalikan kolom bila uji gagal.
- [ ] **Step 2: Jalankan** `DB_DATABASE=qrsar_test php tests/test_optimasi_skema.php`. Expected: FAIL (kolom belum ada).
- [ ] **Step 3: Implementasikan** kolom (pola array `ALTER` yang sudah ada di kedua model), parameter simpan/baca, dan panggilan `ensureSchemaUpdated(true)` setelah restore (setelah `commit` di jalur JSON, setelah loop statement di jalur SQL).
- [ ] **Step 4: Jalankan** uji Step 2 → PASS. Lalu `DB_DATABASE=qrsar_test php database/migrate.php` sukses, dan suite tetap hijau.

### Task 2: Form Laporan Awal: Jenis Kejadian dropdown, Unsur bawaan 1 baris, asal pemberangkatan

**Files:**
- Modify:
  - `models/OperasiModel.php`
  - `views/operasi/form.php`: blok `jenis_kejadian` (±273-281), default unsur (±85-86), select `origin_base_select` (±540).
  - `assets/js/pages/operasi_form.js`: handler Jenis Kejadian; `handleOriginBaseChange` dan inisialisasi asal.
- Test: `tests/test_laporan_awal_form_opt.php`

**Interfaces:**
- Consumes: kolom `asal_berangkat` (Task 1).
- Produces:
  - `OperasiModel::JENIS_KEJADIAN_OPSI = ['Kecelakaan Kapal', 'Kecelakaan Pesawat Udara', 'Kondisi Membahayakan Manusia', 'Bencana', 'Kecelakaan dengan Penanganan Khusus']`.
  - `OperasiModel::jenisKejadianPilihan(?string $nilai): array{pilihan: string, lainnya: string}`:
    - kosong → `['pilihan' => '', 'lainnya' => '']`;
    - ada di daftar → `[nilai, '']`;
    - selain itu → `['__LAINNYA__', nilai]`.

- [ ] **Step 1: Tulis uji gagal**:
  - `jenisKejadianPilihan('Bencana') === ['pilihan'=>'Bencana','lainnya'=>'']`.
  - `jenisKejadianPilihan('Orang Tenggelam') === ['pilihan'=>'__LAINNYA__','lainnya'=>'Orang Tenggelam']` (Review Focus 1).
  - `jenisKejadianPilihan(null)` kosong.
  - Sumber `form.php`:
    - memuat `<select id="jenis_kejadian_select"` beserta opsi `__LAINNYA__`;
    - input teks `name="jenis_kejadian"` tetap ada;
    - tidak ada lagi `list="jenis_kejadian_opsi"`;
    - default unsur `'SMC dan Staf'` dengan jumlah 2, dan tidak ada lagi `'nama_unsur' => 'Staf'`;
    - opsi `origin_base_select` diberi `selected` bila `nama_unit` sama dengan `$operasi['asal_berangkat']` (dicek dengan literal `asal_berangkat` di blok select).
- [ ] **Step 2: Jalankan.** Expected: FAIL.
- [ ] **Step 3: Implementasikan.**
  - Jenis Kejadian:
    - `<select id="jenis_kejadian_select">` berisi opsi kosong "-- Pilih Jenis Kejadian --", 5 opsi dari konstanta, dan "Lainnya…" (`__LAINNYA__`).
    - Input teks `name="jenis_kejadian"` tampil hanya saat Lainnya dipilih.
    - Untuk opsi biasa, JS mengisi input dengan nilai opsi; nilai awal dari `jenisKejadianPilihan`.
  - Origin:
    - `selected` mengutamakan kecocokan `asal_berangkat` (case-insensitive), lalu `is_default`.
    - `origin_name` diisi saat halaman dimuat, tidak hanya saat select berubah.
- [ ] **Step 4: Jalankan** uji → PASS. Jalankan juga `tests/test_alut_kategori_form.php` dan `tests/test_audit_modul_2_input_create.php` → PASS.

### Task 3: Drag-reorder Unsur & Alut (SortableJS, alut per kategori)

**Files:**
- Create: `assets/vendor/sortablejs/Sortable.min.js`
- Modify:
  - `views/operasi/form.php`: muat Sortable sebelum `operasi_form.js`.
  - `assets/js/alut_kategori.js`: fungsi `groups`.
  - `assets/js/pages/operasi_form.js`: `renderUnsurList`, `renderAlutList`, `updateAlutItem`; hapus `moveUnsurRow`/`moveAlutRow`.
- Test: `tests/js/alut_kategori.test.js` (tambah kasus), `tests/test_laporan_awal_form_opt.php` (tambah cek sumber).

**Interfaces:**
- Produces: `AlutKategoriUI.groups(list): Array<{kode: string, label: string, items: Array<{item: object, idx: number}>}>`.
  - Hanya kelompok yang berisi item, urut `URUTAN`.
  - `idx` = indeks di `list` asli; urutan item dalam kelompok = urutan di `list`.

- [ ] **Step 1: Unduh pustaka:** `mkdir -p assets/vendor/sortablejs && curl -fsSL https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js -o assets/vendor/sortablejs/Sortable.min.js`. Expected: berkas > 30 KB dan diawali komentar `/*! Sortable 1.15.6`.
- [ ] **Step 2: Tulis uji gagal (JS):**
  - `groups([{nama_alut:'Senter',kategori:'PALSAR'},{nama_alut:'RIB',kategori:'ALUT_LAUT'},{nama_alut:'Rescue Car',kategori:'ALUT_DARAT'},{nama_alut:'Speedboat',kategori:'ALUT_LAUT'}])` → kode `['ALUT_DARAT','ALUT_LAUT','PALSAR']`, kelompok Laut berisi idx `[1,3]`.
  - Review Focus 3: urutan rata dari `groups` sama dengan `sort(list)`.
  - Sumber PHP: `form.php` memuat `assets/vendor/sortablejs/Sortable.min.js`; `operasi_form.js` memuat `new Sortable(` dan tidak memuat `moveAlutRow(` maupun `moveUnsurRow(`.
- [ ] **Step 3: Jalankan** `node tests/js/alut_kategori.test.js` dan uji PHP → FAIL.
- [ ] **Step 4: Implementasikan.**
  - Pegangan drag ⋮⋮ (`.drag-handle`) di tiap baris.
  - Unsur: satu `Sortable` dengan `handle: '.drag-handle'`; `onEnd` menyusun ulang `currentUnsurList` dari urutan `data-idx` di DOM.
  - Alut:
    - Dirender per `groups()` dengan judul kecil (label kategori).
    - Satu `Sortable` per kelompok, tanpa opsi `group`, sehingga drag hanya di dalam kelompok.
    - `onEnd` menyusun ulang `currentAlutList` dari urutan `data-idx` di semua kelompok.
    - `updateAlutItem(..., 'kategori', v)` lalu `currentAlutList = AlutKategoriUI.sort(currentAlutList)`; item pindah ke akhir kelompok barunya karena sort stabil.
    - Saat dimuat: `currentAlutList = AlutKategoriUI.sort(initial)`.
  - Instance Sortable lama dihancurkan (`destroy()`) sebelum render ulang.
- [ ] **Step 5: Jalankan** uji Step 3 → PASS. Jalankan juga `tests/test_alut_kategori_form.php` → PASS (sesuaikan asersi lama yang mengecek tombol ↑↓ bila ada).

### Task 4: Naskah WA: jarak + radial + asal; korban 0 → "-"

**Files:**
- Modify: `services/WhatsAppTemplateService.php`: `generateLaporanAwalWA` (±164-206), `generateLapbangWA` (±385-399).
- Test: `tests/test_wa_jarak_korban.php`

**Interfaces:**
- Consumes: `$ops['asal_berangkat']` (Task 1).
- Produces:
  - `WhatsAppTemplateService::formatJarakTempuh(array $ops): string`.
  - `WhatsAppTemplateService::jumlahOrang(int $n, string $satuan): string`, dengan 0 → `'-'`, selain itu `"{$n} {$satuan}"`.

- [ ] **Step 1: Tulis uji gagal**. `formatJarakTempuh`:
  - `['jarak'=>'12 NM','radial'=>'277°','asal_berangkat'=>'Kantor SAR Kendari']` → `'12 NM (Radial 277° dari Kantor SAR Kendari)'`;
  - tanpa radial → `'12 NM (dari Kantor SAR Kendari)'`;
  - tanpa asal → `'12 NM (Radial 277°)'`;
  - tanpa jarak → `'-'`.

  Naskah Laporan Awal tanpa korban dan POB 0:
  - memuat `Data Korban: -` dan `• S : -`;
  - tidak memuat `0 orang`.

  Naskah Laporan Awal dengan 1 korban DP: memuat `• DP : 1 orang` dan `• S : -`.

  `generateLapbangWA` (biasa dan `$isPenutupan = true`) tanpa korban:
  - memuat `Data Korban: -` dan `• MD : -`;
  - tidak memuat `0 ORANG`.
- [ ] **Step 2: Jalankan.** Expected: FAIL.
- [ ] **Step 3: Implementasikan** kedua helper dan pakai di header + 4 baris status di kedua generator. Satuan Laporan Awal `orang`, LAPBANG `ORANG`.
- [ ] **Step 4: Jalankan** uji → PASS. Jalankan juga `tests/test_alut_kategori_keluaran.php` dan `tests/test_renops_lapbang_phase1.php` → PASS (perbarui asersi lama yang mengharapkan `0 orang`).

### Task 5: GIS: daftar titik dikelompokkan & kartu ringkas

**Files:**
- Create: `assets/js/gis/naming.js` (modul `QrSarGis.Naming`; dimuat di `views/gis.php` sebelum `gis_master.js`).
- Modify: `assets/js/gis_master.js`: `renderSavedPointsList` (±2596).
- Test: `tests/js/gis_naming.test.js`

**Interfaces:**
- Produces: `QrSarGis.Naming.groupPoints(points): Array<{key: string, label: string, points: object[]}>`.
  - Kunci dan label, berurutan:

    | key | label |
    |---|---|
    | `pangkalan` | `Pangkalan SAR (Kantor/POS/USS)` |
    | `dermaga` | `Dermaga SAR` |
    | `lkp` | `LKP` |
    | `korban` | `Penemuan Korban` |
    | `posko` | `Posko` |
    | `entry_exit` | `Entry / Exit Poin` |
    | `lain` | `Tanpa Kategori` |

  - Pemetaan kategori:
    - `Kantor SAR`/`POS SAR`/`Unit Siaga SAR` → `pangkalan`;
    - `Penemuan Korban`/`Lokasi Penemuan Korban` → `korban`;
    - `Entry Poin`/`Exit Poin` → `entry_exit`;
    - kategori lain yang tak dikenal → `lain`.
  - Hanya kelompok berisi titik yang dikembalikan; urutan titik dipertahankan.

- [ ] **Step 1: Tulis uji gagal** (`require('../../assets/js/gis/naming.js')`):
  - Pemetaan untuk 9 kategori valid + `'Aneh'` → `lain`.
  - Urutan kunci sesuai tabel.
  - Kelompok kosong tidak muncul.
- [ ] **Step 2: Jalankan** `node tests/js/gis_naming.test.js` → FAIL.
- [ ] **Step 3: Implementasikan.**
  - `groupPoints`.
  - Render daftar per kelompok:
    - Header: label, jumlah, tombol buka/tutup.
    - Status buka/tutup disimpan di `localStorage` dengan kunci `qrsar.gis.pointGroups`, dibungkus try/catch.
    - Bawaan: `pangkalan` tertutup, lainnya terbuka.
  - Kartu satu baris: ikon 6×6, nama (truncate), DMS ringkas, dan tombol ikon salin/sembunyikan/edit/hapus.
  - Jarak/arah dari Kansar dan badge operasi dipindah ke atribut `title` kartu.
- [ ] **Step 4: Jalankan** uji → PASS. Cek browser: kelompok bisa dibuka/tutup dan statusnya bertahan setelah reload.

### Task 6: Search area: persegi bisa digeser utuh; warna & transparansi garis/isi terpisah

**Files:**
- Modify:
  - `assets/js/gis_master.js`: `updateLivePolygonVisual` (±3150-3232), swatch & slider (±2717-2760), `startPolygonDrawing` (±2967), payload `handleSavePolygon`, `renderSavedPolygons` (±3294-3370), dan render capture 16:9 bila memakai warna poligon.
  - `views/gis.php`: blok "Penyesuaian Garis & Warna" (±802-830).
- Test: `tests/test_gis_area_style.php` (cek sumber)

**Interfaces:**
- Consumes: kolom `strokeOpacity` (Task 1).
- Produces:
  - Variabel `polyStrokeOpacity` (bawaan 1).
  - Elemen `#polyFillColorSwatches` dan `#polyStrokeOpacitySlider` (min 0.1, max 1, step 0.05).

- [ ] **Step 1: Tulis uji gagal** (cek sumber):
  - `views/gis.php` memuat `id="polyFillColorSwatches"` dan `id="polyStrokeOpacitySlider"`.
  - `gis_master.js`:
    - tidak lagi memuat `polyFillColor = polyStrokeColor;`;
    - memuat `opacity: polyStrokeOpacity` dan `strokeOpacity: polyStrokeOpacity`;
    - memuat penanda pin tengah persegi (kelas `sar-rect-center-pin`).
- [ ] **Step 2: Jalankan.** Expected: FAIL.
- [ ] **Step 3: Implementasikan.**
  - Warna garis dan warna isi dipilih dari swatch masing-masing; tambahkan slider transparansi garis.
  - Semua `L.polygon`/`L.circle` search area memakai `opacity`.
  - Saat menyunting `rectangle`, tambahkan pin tengah `sar-rect-center-pin`. Pada `dragend`:
    - geser semua `activePolygonPoints` sebesar selisih (Δlat, Δlng) dari pusat lama;
    - perbarui `polyShapeCenter`;
    - lalu `updateLivePolygonVisual()`.
- [ ] **Step 4: Jalankan** uji → PASS. Cek browser:
  - persegi digeser utuh;
  - warna garis dan isi berbeda;
  - transparansi garis tersimpan setelah reload.

### Task 7: Jarak lurus terkunci setelah A–B + tombol Reset Garis

**Files:**
- Modify:
  - `assets/js/gis_master.js`: `addMeasurePoint` (±5533), `initMeasureDistanceUI` (±5363).
  - `views/gis.php`: kartu rute (±1140), tombol `#btnResetMeasureLine`.
- Test: `tests/test_gis_area_style.php` (tambah cek sumber)

- [ ] **Step 1: Tulis uji gagal**:
  - `views/gis.php` memuat `id="btnResetMeasureLine"`.
  - `addMeasurePoint` tidak lagi memuat `measurePoints = [{ lat, lng }]` di cabang mode lurus.
  - Ada string status `Garis lengkap — tekan Reset untuk membuat ulang`.
- [ ] **Step 2: Jalankan.** Expected: FAIL.
- [ ] **Step 3: Implementasikan.**
  - Mode `straight` dengan 2 titik: klik diabaikan, lalu `showToast` informatif (maks sekali per 3 detik) dan badge status di atas.
  - Tombol Reset mengosongkan `measurePoints`, memanggil `clearMeasureVisual()`, mereset penghitung/badge, dan tidak menutup kartu.
  - Mode `path` tidak berubah.
- [ ] **Step 4: Jalankan** uji → PASS. Cek browser: klik ketiga tidak mengubah garis; Reset lalu klik baru membuat A.

### Task 8: Flip pola Parallel Track & Creeping Line

**Files:**
- Modify:
  - `assets/js/gis/patterns.js`: `generateFittedPatternInsidePolygon`.
  - `assets/js/gis_master.js`: state `activePatternFlipH/V`, tombol toggle, payload simpan, `editPattern`.
  - `views/gis.php`: tombol `#btnPatternFlipH` dan `#btnPatternFlipV` di form pola, tampil untuk `parallel_track`/`creeping_line`.
- Test: `tests/js/gis_patterns_flip.test.js`

**Interfaces:**
- Consumes: kolom `flipH`/`flipV` (Task 1).
- Produces: `generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount, options)` dengan `options.flipH`/`options.flipV` (boolean) untuk `parallel_track` dan `creeping_line`.

- [ ] **Step 1: Tulis uji gagal** (`const P = require('../../assets/js/gis/patterns.js').QrSarGis.Patterns;` — modul memasang `QrSarGis` pada `this` = `module.exports` di Node):
  - Persegi `[(−4,122),(−4,122.2),(−4.2,122.2),(−4.2,122)]`, `parallel_track`, spasi 2 NM, 5 leg:
    - tanpa flip: waypoint pertama berada di sisi barat (lng < pusat);
    - `flipH`: waypoint pertama di sisi timur;
    - `flipV`: lat waypoint pertama berpindah ke sisi seberang dibanding tanpa flip.
  - Jumlah waypoint sama dengan versi tanpa flip.
  - Review Focus 5: poligon cekung (bentuk L, 6 titik) + `creeping_line` dengan `flipH`+`flipV` → setiap waypoint `isPointInPolygon` (toleransi margin bawaan modul).
- [ ] **Step 2: Jalankan** `node tests/js/gis_patterns_flip.test.js` → FAIL.
- [ ] **Step 3: Implementasikan.**
  - Cerminkan titik poligon terhadap sumbu tengah bbox (lng' = 2·cLng − lng untuk H; lat' = 2·cLat − lat untuk V).
  - Panggil generator yang ada pada poligon cermin.
  - Cerminkan waypoint hasilnya kembali; label tidak diubah.
  - UI: toggle aktif/nonaktif memicu `updatePatternAutoCalculation`. Simpan `flipH`/`flipV`; `editPattern` mengembalikan state toggle.
- [ ] **Step 4: Jalankan** uji → PASS. Cek browser: 4 kombinasi flip pada persegi, simpan, lalu edit → toggle kembali.

### Task 9: Peta blank saat di-zoom

**Files:**
- Modify: `assets/js/gis_master.js` (±1172-1206, konfigurasi `L.tileLayer`), hanya setelah akar masalah terbukti.
- Test: `tests/test_gis_area_style.php` (tambah cek sumber sesuai temuan)

- [ ] **Step 1: Reproduksi** (superpowers:systematic-debugging) di browser `qrsar-test-db`:
  - buka `gis.php`, layer satelit/hybrid, pusat di laut sekitar Kendari;
  - zoom 16 → 22;
  - catat zoom pertama yang blank dan status permintaan tile di `read_network_requests` (404 / gambar kosong);
  - ulangi untuk roadmap & OSM.
- [ ] **Step 2: Rumuskan akar masalah** di ledger. Bila memang tile tidak tersedia di atas zoom Z, perbaikannya `maxNativeZoom: Z` (zoom terakhir yang berisi gambar) untuk layer terkait, dengan `maxZoom: 22` tetap. Bila penyebab lain, tulis Ruling dan perbaiki akarnya.
- [ ] **Step 3: Tulis uji gagal** (cek sumber) yang memaku nilai hasil Step 2, mis. `maxNativeZoom: 18` pada layer `satellite` dan `google_hybrid`. Jalankan → FAIL.
- [ ] **Step 4: Implementasikan, lalu jalankan** uji → PASS. Cek browser: zoom 22 tetap bergambar (tile diperbesar).

### Task 10: Penamaan otomatis titik, search area, pola, rute (+ Area Tujuan rute)

**Files:**
- Modify:
  - `assets/js/gis/naming.js`.
  - `assets/js/gis_master.js`:
    - simpan poligon (±3236);
    - simpan pola (±4836-4845) dan ganti area target;
    - simpan rute (±5723);
    - simpan titik (payload ±2450);
    - select `#measureRouteTargetArea` (isi dari `savedPolygons` operasi aktif);
    - `editRoute` mengembalikan pilihan.
  - `views/gis.php`: placeholder nama dan select Area Tujuan di kartu rute.
- Test: `tests/js/gis_naming.test.js`

**Interfaces:**
- Consumes: kolom `GisRoute.polygonId` (Task 1).
- Produces (`QrSarGis.Naming`):
  - `nextSruName(polygons: object[]): string` → `SRU N`, N = bilangan bulat terkecil ≥1 yang belum dipakai oleh nama berpola `/^SRU (\d+)$/i`.
  - `patternAutoName(areaName: string, patternType: string): string` → `Pola Pencarian Area ${areaName} (${abbr})`.
    - abbr: `parallel_track` → `PS`, `creeping_line` → `CS`, `e_sar`/`sector_search` → `E-SAR`.
    - `areaName` kosong → `Datum LKP`.
  - `isAutoPatternName(name: string): boolean` → cocok `/^Pola Pencarian Area .+ \((PS|CS|E-SAR)\)$/`.
  - `routeAutoName(areaName: string|null, routes: object[]): string` → `Rute ke ${areaName}`; tanpa area → `Rute N` (terkecil yang belum dipakai pola `/^Rute (\d+)$/i`).
  - `pointAutoName(category: string, points: object[]): string`:
    - `LKP` → `LKP`;
    - `Tanpa Kategori` → `Titik N`;
    - lainnya → `${category} N`, N terkecil yang belum dipakai untuk prefiks itu.

- [ ] **Step 1: Tulis uji gagal**:
  - `nextSruName([{name:'SRU 1'},{name:'SRU 3'}]) === 'SRU 2'`.
  - `patternAutoName('SRU 1','creeping_line') === 'Pola Pencarian Area SRU 1 (CS)'`.
  - `isAutoPatternName('Pola Pencarian Area SRU 1 (PS)') === true` dan `isAutoPatternName('Sapu Pantai')` false.
  - `routeAutoName('SRU 2', []) === 'Rute ke SRU 2'` dan `routeAutoName(null,[{name:'Rute 1'}]) === 'Rute 2'`.
  - `pointAutoName('Penemuan Korban',[{label:'Penemuan Korban 1',category:'Penemuan Korban'}]) === 'Penemuan Korban 2'` dan `pointAutoName('LKP',[]) === 'LKP'`.
- [ ] **Step 2: Jalankan** `node tests/js/gis_naming.test.js` → FAIL.
- [ ] **Step 3: Implementasikan** fungsi, lalu sambungkan ke UI. Nama otomatis hanya bila input nama kosong saat simpan.
  - Saat area target pola diganti dan nama saat ini kosong atau `isAutoPatternName`, nama diganti `patternAutoName` (nama ketikan operator tidak disentuh).
  - Placeholder input memperlihatkan nama otomatis yang akan dipakai.
  - Rute menyimpan `polygonId` dari select Area Tujuan.
- [ ] **Step 4: Jalankan** uji → PASS. Cek browser:
  - area baru tanpa nama → `SRU 1`;
  - pola pada area itu → `Pola Pencarian Area SRU 1 (PS)`;
  - rute ber-area → `Rute ke SRU 1`;
  - nama yang diketik tetap.

### Task 11: Impor per SRU di form RENOPS (daftar periksa + gabung)

**Files:**
- Create: `assets/js/renops_import.js` (modul `RenopsImport`).
- Modify:
  - `views/operasi/renops_form.php`: muat modul sebelum `renops_form.js`; tombol utama modal jadi "Impor per SRU"; wadah `#gis-import-checklist`.
  - `assets/js/pages/renops_form.js`: ganti isi `importAllPolygonsToSrus` agar memakai modul; nama SRU = nama area di `importPolygonToSru`/`importPolygonToNewSru`.
- Test: `tests/js/renops_import.test.js`

**Interfaces:**
- Consumes: `GisRoute.polygonId` (Task 1; ikut di bundle `/api/gis/bundle` karena getter `SELECT *`).
- Produces (`RenopsImport`):
  - `checklist(polygons, patterns, routes): Array<{id: string, name: string, pattern: object|null, route: object|null}>`:
    - pola dicocokkan lewat `polygonId === poly.id`, atau `polygonName === poly.name` sebagai cadangan;
    - rute lewat `polygonId === poly.id`;
    - urutan mengikuti `polygons`.
  - `mergeAreasIntoSrus(srus: object[], items: object[], describe: (item) => object): object[]`:
    - mengembalikan larik baru;
    - baris SRU cocok bila `norm(nama_sru) === norm(item.name)` atau `nama_sru` berpola `/^SRU\s*\d+\s*-\s*(.+)$/i` dengan grup 1 `norm === norm(item.name)`;
    - `norm` = trim + lowercase;
    - baris cocok di-`Object.assign` dengan `describe(item)` (field lain yang tidak dikembalikan `describe` tetap);
    - item tanpa pasangan ditambahkan di akhir;
    - baris lain tidak disentuh.

- [ ] **Step 1: Tulis uji gagal**:
  - `checklist` menandai pola & rute yang tepat, dan `null` bila tidak ada.
  - Review Focus 4: `mergeAreasIntoSrus`:
    - pada `[{nama_sru:'SRU 1 - SRU 1', unsur:'X'}, {nama_sru:'Tim Darat'}]` dengan items `SRU 1`, `SRU 2` → 3 baris: baris 0 diperbarui (`unsur` tetap `'X'`), `Tim Darat` utuh, `SRU 2` ditambahkan;
    - menjalankan hasilnya sekali lagi dengan items yang sama → panjang tetap 3.
- [ ] **Step 2: Jalankan** `node tests/js/renops_import.test.js` → FAIL.
- [ ] **Step 3: Implementasikan** modul.
  - `describe` di `renops_form.js` menyusun `nama_sru` (= nama area), `koordinat_area`, `luas_area`, dan `pola_pencarian`/`total_sapuan`/`track_spacing` dari `getPatternDetails(pattern)`.
  - Bila ada rute, ringkasan rute (`Rute: nama, panjang, kecepatan`) ditambahkan ke `koordinat_area`.
  - Gunakan formatter yang sudah ada di file itu.
  - Konfirmasi lama "konversi menimpa" dihapus karena sekarang menggabung.
  - Daftar periksa dirender di atas tab modal: nama area, ✓/✗ Pola, ✓/✗ Rute.
- [ ] **Step 4: Jalankan** uji → PASS. Jalankan juga `tests/test_renops_lapbang_phase2_3.php` dan `tests/test_renops_distance_unit_selection.php` → PASS. Cek browser: impor dua kali tidak menduplikasi; SRU manual utuh.

### Task 12: Verifikasi akhir

- [ ] **Step 1:** `DB_DATABASE=qrsar_test php tests/run_all.php` → semua PASS; `for f in tests/js/*.test.js; do node "$f"; done` → 0 FAIL.
- [ ] **Step 2:** Pindai fatal tersamar: tidak ada uji yang keluarannya memuat `QR-SAR EXCEPTION` (kecuali `test_gis_ssot_and_map_rendering.php` yang sudah rusak sebelumnya).
- [ ] **Step 3:** `php database/migrate.php` di DB utama `qrsar` → sukses; jalankan ulang → tetap sukses (idempoten).
- [ ] **Step 4:** Rebuild Tailwind (perintah di Global Constraints) bila ada kelas baru.
- [ ] **Step 5:** Cek browser `qrsar-test-db` sesuai bagian 7 spec. Tidak ada error konsol baru.
