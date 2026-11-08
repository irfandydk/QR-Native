# Kategori Alut & Palsar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Setiap item alut punya kategori pasti (`ALUT_DARAT`, `ALUT_LAUT`, `ALUT_UDARA`, `PALSAR`) di master dan di setiap laporan, lalu dipakai Laporan, BRA SAR, dan Dokumen RENOPS menggantikan tebakan nama.

**Architecture:**
- Satu helper `AlutKategori` menjadi sumber tunggal kode, label, urutan, normalisasi, resolver, dan cadangan kata kunci.
- Migrasi menambah kolom di `MasterAlut` dan `DetailAlut`, mengisi master, lalu `AlutKategori::backfill()` mengisi semua laporan lama sekali (beku).
- Model menormalkan kategori setiap kali menyimpan. Keluaran (WA, cetak, BRA, RENOPS) membaca kategori yang tersimpan.

**Tech Stack:** PHP 8.5 (`declare(strict_types=1)`), MySQL/InnoDB, vanilla JS, test `tests/test_*.php` + `node tests/js/*.test.js`.

**Spec:** `docs/superpowers/specs/2026-11-07-kategori-alut-palsar-design.md` (keputusan U1–U4, §5.7).

## Global Constraints

- **Jangan commit.** Working tree `main` berisi WIP pengguna; ubah hanya hunk yang dibutuhkan. Langkah "Commit" = jalankan test terkait.
- Test hanya di DB test: `DB_DATABASE=qrsar_test php tests/<file>.php`; migrasi test: `DB_DATABASE=qrsar_test php database/migrate.php`.
- Kode kategori persis: `ALUT_DARAT`, `ALUT_LAUT`, `ALUT_UDARA`, `PALSAR`. Label persis: `ALUT Darat`, `ALUT Laut`, `ALUT Udara`, `PALSAR`. Urutan baku: Darat, Laut, Udara, PALSAR.
- Default master/kolom: `PALSAR`. `DetailAlut.kategori` boleh NULL hanya untuk data yang belum di-backfill.
- Kata kunci cadangan (kata utuh, tanpa beda huruf besar/kecil), diperiksa berurutan Laut → Udara → Darat, selain itu `PALSAR`:
  - **Laut:** `kapal, rib, perahu, boat, speed, speedboat, longboat`
  - **Udara:** `heli, helikopter, pesawat, drone`
  - **Darat:** `car, truck, truk, mobil, motor, ambulans`
- Isian master awal (nama persis, tanpa beda huruf besar/kecil):
  - **ALUT_DARAT:** Rescue Car, Rescue Carrier
  - **ALUT_LAUT:** Rescue Boat 210, Rescue Boat 307, Rigid Inflatable Boat (RIB), Rubber Boat, Longboat Masyarakat, KN SAR Pacitan
  - **ALUT_UDARA:** Drone Thermal
  - **PALSAR:** Aqua Eye, Palsar Medis, Palsar Evakuasi, Palsar Komunikasi, Palsar Selam, Palsar Mounteneering, Kantong Jenazah
- Judul naskah WA/BRA tetap "ALUT DAN PALSAR" (U3). Item di dalamnya diurutkan per kategori; format baris tidak berubah.
- BRA "menggunakan …" hanya menyebut kendaraan: kategori `ALUT_*` **kecuali** nama yang mengandung kata utuh `drone` (U4).
- Semua keluaran HTML lewat `e()`.

## Review Focus

1. Item alut lama tanpa kategori (restore backup lama, JSON belum di-backfill) tidak boleh membuat form, WA, BRA, atau dokumen error, dan tetap mendapat kategori lewat resolver. → test di Task 1 dan Task 5.
2. Nama ketik-bebas yang sama dengan nama master dengan huruf/spasi berbeda ("  rescue car ") memakai kategori master. → test di Task 1.
3. Kategori tidak dikenal dari input form/API ("ALUT", "laut", "") dinormalkan, dan nilai salah tidak tersimpan. → test di Task 1 dan Task 3.
4. Menjalankan migrasi/backfill dua kali tidak mengubah kategori yang sudah ada, termasuk yang sudah dikoreksi manual. → test di Task 1.
5. Kategori master diubah setelah laporan disimpan → laporan lama tetap memakai kategori lamanya. → test di Task 3.

---

### Task 1: Helper `AlutKategori` + migrasi + backfill

**Files:**
- Create: `helpers/AlutKategori.php`
- Create: `database/migrations/2026_11_07_add_kategori_alut.sql`
- Modify: `database/migrate.php` (panggil backfill setelah migrasi SQL, sebelum `=== All database migrations completed`)
- Test: `tests/test_alut_kategori.php`

**Interfaces:**
- Produces:
  - Konstanta `AlutKategori::DARAT='ALUT_DARAT'`, `LAUT='ALUT_LAUT'`, `UDARA='ALUT_UDARA'`, `PALSAR='PALSAR'`, `URUTAN = [DARAT, LAUT, UDARA, PALSAR]`, `LABEL = [kode => label]`.
  - `AlutKategori::normalize(mixed $v): ?string` → kode valid (tanpa beda huruf besar/kecil, di-trim), atau null.
  - `AlutKategori::tebak(string $nama): string` → hasil kata kunci.
  - `AlutKategori::resolve(array $item): string` → urutan spec §5.3: `kategori` item valid → master by `id_alut` > 0 → master by nama (trim, tanpa beda huruf besar/kecil) → `tebak`. Peta master di-cache statis per request.
  - `AlutKategori::resetCache(): void` (untuk test).
  - `AlutKategori::normalizeList(array $items): array` → setiap item mendapat `kategori` = `resolve(item)`.
  - `AlutKategori::sort(array $items): array` → stabil, menurut `URUTAN` (memakai `resolve`).
  - `AlutKategori::isAlut(string $kode): bool` → diawali `ALUT_`.
  - `AlutKategori::label(string $kode): string`.
  - `AlutKategori::backfill(PDO $db): array{detail_alut:int, renops:int, laporan:int}` → mengisi `DetailAlut.kategori IS NULL`, dan item JSON `detail_alut` di `Renops` dan `LaporanHarian` yang belum punya `kategori` valid. Idempoten. Mengembalikan jumlah baris yang diubah.

- [ ] **Step 1: Tulis test yang gagal** (`tests/test_alut_kategori.php`, pola `assertCondition` + `exit`):

```php
assertCondition(AlutKategori::normalize(' alut_laut ') === 'ALUT_LAUT' && AlutKategori::normalize('ALUT') === null && AlutKategori::normalize('') === null, 'normalize');
assertCondition(AlutKategori::tebak('Drone Thermal') === 'ALUT_UDARA' && AlutKategori::tebak('KN SAR Pacitan') === 'PALSAR' && AlutKategori::tebak('Longboat Warga') === 'ALUT_LAUT' && AlutKategori::tebak('Carabiner') === 'PALSAR', 'tebak kata utuh');
// master fixture: INSERT MasterAlut ('Uji KN Pelangi','Unit','ALUT_LAUT')
AlutKategori::resetCache();
assertCondition(AlutKategori::resolve(['nama_alut' => '  uji kn pelangi ']) === 'ALUT_LAUT', 'nama ketik-bebas cocok master (Review Focus 2)');
assertCondition(AlutKategori::resolve(['nama_alut' => 'Uji KN Pelangi', 'kategori' => 'PALSAR']) === 'PALSAR', 'kategori item menang atas master');
assertCondition(AlutKategori::resolve(['nama_alut' => 'X', 'kategori' => 'ngawur']) === 'PALSAR', 'kategori tak dikenal → resolver (Review Focus 3)');
assertCondition(array_column(AlutKategori::sort([['nama_alut'=>'Palsar Medis'],['nama_alut'=>'Drone Thermal'],['nama_alut'=>'Rescue Car'],['nama_alut'=>'Rubber Boat']]), 'nama_alut') === ['Rescue Car','Rubber Boat','Drone Thermal','Palsar Medis'], 'urutan Darat, Laut, Udara, PALSAR');
// fixture backfill (lewat SQL langsung, melewati model): operasi + DetailAlut ('Rescue Car', kategori NULL) & ('Rubber Boat', kategori 'PALSAR' manual);
// Renops.detail_alut = '[{"nama_alut":"Drone Thermal","satuan":"Unit","jumlah_alut":1}]'; LaporanHarian.detail_alut = '[{"nama_alut":"Senter","satuan":"Buah","jumlah_alut":2}]'
$r1 = AlutKategori::backfill(getDB());
$detail = getDB()->query("SELECT nama_alut, kategori FROM DetailAlut WHERE id_operasi = {$idOps} ORDER BY id_detail_alut")->fetchAll(PDO::FETCH_KEY_PAIR);
$jRen = json_decode(getDB()->query("SELECT detail_alut FROM Renops WHERE id_renops = {$idR}")->fetchColumn(), true);
$jLap = json_decode(getDB()->query("SELECT detail_alut FROM LaporanHarian WHERE id_laporan = {$idL}")->fetchColumn(), true);
assertCondition($detail === ['Rescue Car' => 'ALUT_DARAT', 'Rubber Boat' => 'PALSAR'] && $jRen[0]['kategori'] === 'ALUT_UDARA' && $jLap[0]['kategori'] === 'PALSAR'
    && $r1['detail_alut'] >= 1 && $r1['renops'] >= 1 && $r1['laporan'] >= 1, 'backfill mengisi data lama, kategori manual dipertahankan');
$r2 = AlutKategori::backfill(getDB());
assertCondition($r2 === ['detail_alut' => 0, 'renops' => 0, 'laporan' => 0], 'backfill idempoten (Review Focus 4)');
$master = getDB()->query("SELECT LOWER(nama_alut), kategori FROM MasterAlut WHERE LOWER(nama_alut) IN ('rescue car','kn sar pacitan','aqua eye')")->fetchAll(PDO::FETCH_KEY_PAIR);
assertCondition(array_diff_assoc($master, ['rescue car' => 'ALUT_DARAT', 'kn sar pacitan' => 'ALUT_LAUT', 'aqua eye' => 'PALSAR']) === [], 'isian master awal dari migrasi (baris yang ada di DB test)');
```
  Fixture data dihapus di `finally`.

- [ ] **Step 2: Jalankan test.** Harus FAIL dengan "Class AlutKategori not found".

- [ ] **Step 3: Migrasi SQL.**
  - `ALTER TABLE MasterAlut ADD COLUMN kategori VARCHAR(20) NOT NULL DEFAULT 'PALSAR' AFTER satuan`
  - `ALTER TABLE DetailAlut ADD COLUMN kategori VARCHAR(20) NULL DEFAULT NULL AFTER satuan`
  - `UPDATE` per nama sesuai isian master awal (Global Constraints).
  - Item master lain tidak diubah: tetap `PALSAR`, lalu dikoreksi lewat langkah PHP berikut.

- [ ] **Step 4: Implementasikan `helpers/AlutKategori.php`, lalu sambungkan backfill di `database/migrate.php`.**
  - Di `migrate.php`, tambah blok `[RUN] Backfill kategori Alut…` yang:
    1. Memanggil `AlutKategori::backfill($db)`.
    2. Mengoreksi item master selain isian awal yang masih `PALSAR` padahal `tebak(nama)` ≠ `PALSAR`. Ini hanya dijalankan sekali, ditandai dengan keberadaan kolom yang baru dibuat di run yang sama, atau lewat tabel `migrations`. Pilih cara yang idempoten dan tulis alasannya di komentar.
  - Pembaruan JSON memakai `json_encode(..., JSON_UNESCAPED_UNICODE)` seperti model.

- [ ] **Step 5: Jalankan migrasi test, lalu test.** `DB_DATABASE=qrsar_test php database/migrate.php`, lalu `DB_DATABASE=qrsar_test php tests/test_alut_kategori.php`. Harus PASS.

---

### Task 2: Master Data Alut dengan kategori

**Files:**
- Modify: `models/MasterModel.php` (`createAlut`, `updateAlut`)
- Modify: `controllers/MasterController.php` (`add_alut`, `edit_alut`)
- Modify: `api/controllers/MasterApiController.php` (endpoint alut, bila ada create/update)
- Modify: `views/master/partials/tab_alut.php` (form tambah + kolom tabel), `views/master/partials/modals.php` (modal edit + `openEditAlutModal`)
- Test: `tests/test_master_alut_kategori.php`

**Interfaces:**
- Consumes: `AlutKategori::normalize`, `LABEL`, `URUTAN`.
- Produces: `MasterModel::createAlut(string|array $nama, string $satuan = 'Unit', string $kategori = 'PALSAR'): int` dan `updateAlut(int $id, string|array $nama, string $satuan = 'Unit', string $kategori = 'PALSAR'): bool`.
  - Bila `$nama` berupa array, kunci `kategori` ikut dibaca.
  - Kategori tidak valid → `InvalidArgumentException('Kategori alut tidak valid.')`.

- [ ] **Step 1: Tulis test yang gagal.** Create dengan `'ALUT_UDARA'` tersimpan. Update ke `'PALSAR'` tersimpan. Create dengan `'ngawur'` melempar `InvalidArgumentException`. Static check: `tab_alut.php` memuat `name="kategori"` beserta keempat label, `modals.php` memuat `id="edit_kategori_alut"`, dan tabel menampilkan `AlutKategori::label(...)`.
- [ ] **Step 2: Jalankan test.** Harus FAIL.
- [ ] **Step 3: Implementasikan.**
  - Select kategori wajib di form tambah dan modal edit (opsi dari `AlutKategori::URUTAN`/`LABEL`).
  - Controller dan API meneruskan `kategori`, lalu `InvalidArgumentException` → flash/422.
  - Tabel master menampilkan label kategori (di-escape).
  - `openEditAlutModal(id, nama, satuan, kategori)` mengisi select.
- [ ] **Step 4: Jalankan test.** Harus PASS. Jalankan juga `tests/test_master_data_integrity.php`, harus PASS.

---

### Task 3: Kategori ikut tersimpan di setiap laporan

**Files:**
- Modify: `services/OperasiRelationService.php` (`applyDifferentialAlut`: kolom `kategori` di UPDATE/INSERT)
- Modify: `models/OperasiModel.php` (`formatAlutText` mengurutkan dengan `AlutKategori::sort`; `prepareLaporanAwal`/create/update memakai `normalizeList` pada `detail_alut`)
- Modify: `models/RenopsModel.php` (`resolveUnsurAlut`) dan `models/LaporanModel.php` (resolusi alut baris ~76): `detail_alut` = `AlutKategori::normalizeList(...)` sebelum `json_encode`
- Test: `tests/test_alut_kategori_simpan.php`

**Interfaces:**
- Consumes: `AlutKategori::normalizeList`, `sort`.
- Produces: setiap item `detail_alut` yang tersimpan (tabel `DetailAlut` dan JSON RENOPS/LAPBANG) selalu punya `kategori` valid.

- [ ] **Step 1: Tulis test yang gagal.**
  - `OperasiModel::create` dengan `detail_alut` `[{nama_alut:'Rescue Car', …}, {nama_alut:'Senter', kategori:'alut_darat'}, {nama_alut:'X', kategori:'ngawur'}]`. Assert `DetailAlut.kategori` = `ALUT_DARAT`, `ALUT_DARAT`, `PALSAR`.
  - `RenopsModel::create` dan `LaporanModel::create` dengan item tanpa kategori. Assert JSON tersimpan punya `kategori`.
  - `alut_palsar` (teks) Laporan Awal diurutkan Darat → Laut → Udara → PALSAR.
  - Review Focus 5: ubah kategori master 'Rescue Car' menjadi PALSAR setelah simpan, lalu `OperasiModel::getById(...)['detail_alut'][0]['kategori'] === 'ALUT_DARAT'`. Kembalikan nilai master di `finally`.
- [ ] **Step 2: Jalankan test.** Harus FAIL.
- [ ] **Step 3: Implementasikan.** Tanpa mengubah perilaku lain dari `applyDifferentialAlut`, termasuk klaim id dan pembersihan.
- [ ] **Step 4: Jalankan test.** Harus PASS. Regresi: `tests/test_crud_audit_end_to_end.php`, `tests/test_renops_lapbang_phase2_3.php`, `tests/test_audit_modul_2_input_create.php`, semua harus PASS.

---

### Task 4: Pilihan kategori di form Laporan Awal, RENOPS, LAPBANG

**Files:**
- Modify: `assets/js/pages/operasi_form.js`, `assets/js/pages/renops_form.js`, `assets/js/pages/sitrep_form.js`
- Modify: `views/operasi/form.php`, `views/operasi/renops_form.php`, `views/operasi/sitrep_form.php` (select input kategori di samping `alut-input-satuan` + konfigurasi kategori untuk JS)
- Test: `tests/test_alut_kategori_form.php` (statis) + `node --check` ketiga file JS

**Interfaces:**
- Consumes: daftar master (`MasterModel::getAlut()`, kini berisi `kategori`). `AlutKategori::LABEL`/`URUTAN` disuntik ke JS sebagai `window.ALUT_KATEGORI = [{kode, label}, …]` (satu definisi per halaman, dari PHP).
- Produces: setiap item di `currentAlutList` punya `kategori`, dan dikirim di `detail_alut_json`.

- [ ] **Step 1: Tulis test yang gagal** (statis):
  - Ketiga view memuat `id="alut-input-kategori"` dan `window.ALUT_KATEGORI`.
  - Ketiga JS memuat `updateAlutItem(${idx}, 'kategori', this.value)`.
  - `pickMasterAlut` mengisi `alut-input-kategori` dari item master.
  - Item baru menyimpan `kategori:` dari input.
  - Teks ringkas dibentuk dari daftar yang diurutkan per kategori, mengikuti `window.ALUT_KATEGORI`.
- [ ] **Step 2: Jalankan test.** Harus FAIL.
- [ ] **Step 3: Implementasikan dengan pola yang sama di ketiga form.**
  - Nama ketik-bebas tanpa pilihan manual mengisi select dengan tebakan kata kunci. Daftar kata kunci di JS disuntik dari PHP (`AlutKategori`), tidak ditulis ulang.
  - Item lama (draf localStorage atau data edit) tanpa kategori ditampilkan dengan tebakan.
- [ ] **Step 4: Jalankan test dan pengecekan.** Test PASS, `node --check` ketiganya OK, dan `tests/test_phase10_js_modular.php` PASS.

---

### Task 5: Naskah WA, cetak, dan tab Kekuatan

**Files:**
- Modify: `services/WhatsAppTemplateService.php`: tiga blok duplikat "Alut & Palsar" (Laporan Awal, RENOPS, LAPBANG) diganti satu `private static function formatAlutLines(array $alut): string` yang memakai `AlutKategori::sort`. Format baris tetap `• {jumlah} {satuan} {nama}`, dan kosong tetap `• Belum ada data alut`.
- Modify: `views/operasi/laporan_awal_print.php` (~501), `views/operasi/sitrep_print.php` (alutList), `views/operasi/partials/tab_kekuatan.php`: urutkan dengan `AlutKategori::sort`, dan tampilkan label kategori kecil per item.
- Test: `tests/test_alut_kategori_keluaran.php`

**Interfaces:**
- Consumes: `AlutKategori::sort`, `label`, `resolve`.

- [ ] **Step 1: Tulis test yang gagal.**
  - `generateLaporanAwalWA($ops, [], [], [palsar, udara, darat])`: judul `ALUT DAN PALSAR` tetap ada, dan urutan baris darat → udara → palsar.
  - Hal yang sama untuk RENOPS dan LAPBANG WA.
  - Item tanpa kategori tidak error (Review Focus 1).
  - Render `tab_kekuatan.php` dengan fixture memuat label `ALUT Laut`.
- [ ] **Step 2: Jalankan test.** Harus FAIL.
- [ ] **Step 3: Implementasikan.**
- [ ] **Step 4: Jalankan test.** Harus PASS. Regresi: `tests/test_audit_modul_5_verify_qr_pdf.php` dan `tests/test_renops_lapbang_phase2_3.php`, keduanya harus PASS.

---

### Task 6: Dokumen RENOPS memakai kategori

**Files:**
- Modify: `services/RenopsDokumenService.php`:
  - `splitAlut`: Sarana = `isAlut(resolve(item))`, Peralatan = sisanya.
  - `grupAset(string $nama)` diganti grup dari kategori item (`ALUT_DARAT` → 'Darat', `ALUT_LAUT` → 'Laut', `ALUT_UDARA` → 'Udara').
  - Daftar kata kunci di service dihapus (pindah ke `AlutKategori`).
  - Tabel aset tetap hanya Sarana, diurutkan Darat → Laut → Udara.
- Modify: `tests/test_renops_dokumen_resolve.php`: assert lama yang bergantung kata kunci disesuaikan.
  - `Drone` kini Sarana/Udara.
  - Item dengan `kategori` eksplisit `PALSAR` bernama "Rescue Car" masuk Peralatan (kategori menang atas nama).

**Interfaces:**
- Consumes: `AlutKategori::resolve`, `isAlut`, `sort`.
- Produces: kontrak data dokumen tidak berubah.
  - Bila `RenopsDokumenService::splitAlut(list $alut): array{sarana, peralatan}` tetap publik, perilakunya berbasis kategori.
  - `grupAset` boleh dihapus bila tidak dipakai lagi. Perbarui pemanggil dan test.

- [ ] **Step 1: Ubah test dulu.** Tambahkan assert kategori menang atas nama dan `Drone Thermal` → Sarana grup Udara. Jalankan, harus FAIL.
- [ ] **Step 2: Implementasikan.**
- [ ] **Step 3: Jalankan test.** `tests/test_renops_dokumen_resolve.php`, `tests/test_renops_dokumen_publish.php`, dan `tests/test_renops_smeac_render.php` harus PASS.

---

### Task 7: BRA SAR memakai kategori

**Files:**
- Modify: `assets/js/bra/bra_templates.js`:
  - `alutItems(rows)` mengurutkan baris menurut `kategori`, dengan urutan Darat, Laut, Udara, PALSAR, lalu tanpa kategori di akhir. Format baris tidak berubah.
  - Ganti pemakaian `isTransportAlut(a.nama_alut)` di kalimat "menggunakan" (baris ~180) dengan fungsi baru `isKendaraanAlut(row)`:
    - `row.kategori` diawali `ALUT_` **dan** nama tidak mengandung kata utuh `DRONE`.
    - Bila `row.kategori` kosong, cadangan ke `isTransportAlut(row.nama_alut)` yang lama (data lama yang lolos backfill).
  - Ekspor `isKendaraanAlut`.
- Modify: `tests/js/bra_templates.test.js`
- Note: data BRA berasal dari `DetailAlut` dan JSON yang sudah punya kategori setelah Task 1 dan Task 3. Cadangan JS hanya untuk sisa data lama. Ini menyimpang dari spec §5.6 ("diselesaikan server") demi perubahan terkecil, dan dicatat di laporan.

- [ ] **Step 1: Tulis test JS yang gagal.**
  - `isKendaraanAlut({nama_alut:'Drone Thermal', kategori:'ALUT_UDARA'}) === false`
  - `isKendaraanAlut({nama_alut:'KN SAR Pacitan', kategori:'ALUT_LAUT'}) === true`
  - `isKendaraanAlut({nama_alut:'Rescue Car'}) === true` (cadangan)
  - `isKendaraanAlut({nama_alut:'Palsar Medis', kategori:'PALSAR'}) === false`
  - `alutItems` mengurutkan PALSAR ke akhir.
- [ ] **Step 2: Jalankan.** `node tests/js/bra_templates.test.js` harus ada FAIL.
- [ ] **Step 3: Implementasikan.**
- [ ] **Step 4: Jalankan.** Test JS semua PASS. `DB_DATABASE=qrsar_test php tests/test_bra_perfection.php` dan `tests/test_bra_mapel.php` harus PASS.

---

### Task 8: Verifikasi akhir

- [ ] **Step 1:** `DB_DATABASE=qrsar_test php tests/run_all.php` harus 0 FAIL. `node tests/js/bra_templates.test.js` harus semua PASS.
- [ ] **Step 2: Jalankan migrasi di DB utama `qrsar`** (`php database/migrate.php`). Catat jumlah backfill.
- [ ] **Step 3: Browser (server test `qrsar-test-db`).**
  1. Master Data: ubah kategori satu item.
  2. Form Laporan Awal: pilih item master sehingga kategori terisi otomatis, lalu ketik item bebas dan ubah kategorinya. Simpan, lalu cek naskah WA berurutan.
  3. RENOPS: Sarana dan Peralatan serta tabel Aset per matra benar.
  4. BRA: kalimat "menggunakan" tanpa drone.
