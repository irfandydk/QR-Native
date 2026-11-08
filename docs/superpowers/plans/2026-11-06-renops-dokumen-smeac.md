# Dokumen RENOPS SMEAC (Draf, Versi Terbit & QR) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tombol cetak RENOPS menghasilkan dokumen SMEAC A4 landscape seperti lampiran, per hari, dengan data dari hierarki laporan. Dokumen punya editor draf, versi terbit yang dibekukan, foto dokumentasi, dan QR yang bisa diverifikasi (SAH / telah diubah / tidak valid).

**Architecture:**
- Data dokumen dibentuk oleh satu resolver (`RenopsDokumenService::resolve`): lapisan hierarki (Laporan Awal → LAPBANG H(N-1) → RENOPS HN), ditimpa suntingan draf.
- Satu partial PHP merender struktur itu untuk tiga mode: editor (draf), cetak internal, dan halaman publik. Versi terbit menyimpan struktur beku di tabel `RenopsDokumen`, berikut salinan peta dan foto.
- Verifikasi QR mencari token di tabel itu, lalu jatuh ke jalur token lama (`DokumenToken`).

**Tech Stack:**
- PHP 8.5 (`declare(strict_types=1)`), MySQL/InnoDB, ekstensi GD.
- View PHP + Tailwind (`assets/css/tailwind.css`), JS vanilla.
- `qrcode.min.js` + `html2pdf` vendor yang sudah ada.

**Spec:** `docs/superpowers/specs/2026-11-06-renops-dokumen-smeac-design.md` (keputusan K1–K16). Baca bersama rencana ini.

## Global Constraints

- **Jangan commit.** Pengguna commit sendiri saat siap. Langkah "Commit" diganti "jalankan test terkait".
- Test: skrip `tests/test_*.php` dengan pola `require_once __DIR__ . '/bootstrap.php'`, `assertCondition`, `exit(0|1)`. Jalankan di database test:
  - `DB_DATABASE=qrsar_test php database/migrate.php`
  - `DB_DATABASE=qrsar_test php tests/<file>.php`
- Bagian HTTP hanya jalan bila `QRSAR_TEST_URL` diisi (pola `tests/test_http_security.php`).
- Tidak ada dependensi baru (Composer/npm).
- Semua teks dari data atau suntingan dirender lewat `e()` (+ `nl2br` untuk teks multi-baris). Tidak pernah HTML mentah, karena halaman publik bisa dibuka tanpa login.
- Status versi: `DRAF` (versi 0) | `TERBIT` | `DIGANTIKAN`. Satu `DRAF` dan paling banyak satu `TERBIT` per `id_renops`.
- Token versi: 16 karakter hex huruf besar = `strtoupper(substr(hash_hmac('sha256', "RENOPS_DOC:{$idRenops}:{$versi}:{$contentHash}", APP_KEY), 0, 16))`.
- Foto:
  - Hanya JPEG/PNG/WebP, dicek dengan `finfo` (bukan ekstensi).
  - Maksimal `10485760` byte per berkas dan `12` foto per RENOPS.
  - Di-encode ulang ke JPEG, sisi terpanjang `1600` px.
  - Nama berkas `bin2hex(random_bytes(16)) . '.jpg'` di `uploads/renops_foto/`.
- Salinan beku saat terbit: `uploads/renops_dokumen/<hex32>.<ext>`. Nama acak wajib, karena `uploads/` disajikan statis.
- Halaman: A4 landscape, margin atas/kanan/kiri `20mm`, bawah `18mm`. Header berjalan `Rencana Operasi SAR Hari ke-{N} | {satker_nama}`. Footer `Halaman {X} dari {Y}`.
- Tabel aset hanya berkolom `Aset | Jumlah` (tanpa Total Endurance).
- Default Tugas SRU: `Pencarian, pertolongan, dan evakuasi`.
- Kata kunci Sarana (case-insensitive, cocok sebagian nama): `car, truck, truk, mobil, motor, ambulans, kapal, rib, perahu, boat, speed, heli, pesawat`. Selain itu masuk Peralatan.
- Grup aset dari nama:
  - **Laut:** `kapal, rib, perahu, boat, speed`
  - **Udara:** `heli, pesawat, drone`
  - **Darat:** `car, truck, truk, mobil, motor, ambulans`
  - Selain itu `''`.
- Operasi CLOSED: data RENOPS tetap terkunci, tetapi draf, foto, terbit, dan revisi dokumen **diizinkan** (K16).
- Semua user login boleh menyunting dan menerbitkan (K13). Hapus RENOPS tetap khusus admin.

## Kontrak Data Dokumen (dipakai Task 3–9)

`RenopsDokumenService::resolve()` mengembalikan, dan `konten_json` versi TERBIT menyimpan, array berikut:

```php
[
  'meta' => ['id_renops'=>int, 'id_operasi'=>int, 'hari_num'=>int, 'hari_label'=>string /* "1" atau "7 + 1" */,
             'tanggal'=>'Y-m-d', 'nomor'=>string, 'satker_nama'=>string, 'kota'=>'Kendari',
             'jenis_kejadian'=>string, 'nama_operasi'=>string, 'closed'=>bool, 'source_hash'=>string],
  'sections' => [   // KUNCI = kunci suntingan draf
    'awareness'=>string, 'kronologi'=>string, 'jenis_operasi'=>string,
    'data_kejadian'=>list<array{label:string,value:string}>,
    'upaya_sebelumnya'=>string, 'koordinasi'=>list<string>, 'koordinasi_instansi'=>list<string>,
    'persiapan_personel'=>list<string>, 'sarana'=>list<string>, 'peralatan'=>list<string>,
    'mission'=>string, 'waktu'=>string, 'area_pencarian'=>string,
    'aset'=>list<array{grup:string,aset:string,jumlah:string}>,
    'sru'=>list<array{unsur:string,tugas:string,area:string,pola:string,track_spacing:string}>,
    'rescue_plan'=>string, 'briefing'=>string, 'operation_stage'=>string,
    'administrasi'=>list<string>, 'logistik'=>list<string>,
    'struktur'=>array{sc:string,smc:string,osc:string,staf_operasi:string,staf_admin:string,staf_logistik:string,
                      staf_intelijen:string,staf_humas:string,staf_komunikasi:string,
                      sru:list<array{nama:string,keterangan:string}>},
  ],
  'edited' => list<string>,            // kunci sections yang berasal dari suntingan draf
  'peta'   => ['path'=>?string, 'sumber'=>'RENOPS'|'LAPORAN_AWAL'|null], // path relatif dari root, mis. "uploads/…"
  'foto'   => list<array{id_foto:int, path:string, keterangan:string}>,
  'kontak' => ['telp'=>string, 'email'=>string, 'alamat'=>string],     // dari AppSettings
  'pengesahan' => ['kota'=>string, 'tanggal'=>'Y-m-d', 'smc_nama'=>string, 'smc_pangkat'=>string, 'smc_nip'=>string],
]
```

`konten_json` baris DRAF hanya berisi `{"sections": {<kunci>: <nilai>}}` (suntingan saja).

## Review Focus

1. Suntingan berisi HTML/`<script>` lalu dibuka di halaman publik: harus tampil sebagai teks, tidak dieksekusi. Ditest di Task 6.
2. Operasi tanpa korban, tanpa SRU, tanpa alut, tanpa peta: dokumen tetap tampil dengan `-` atau kalimat default, tanpa warning PHP. Ditest di Task 3 dan Task 6.
3. Operasi setelah reopen dengan `hari_ke` `H7 + 1`: hari dan sumber LAPBANG dipilih benar. Ditest di Task 2.
4. Peta di-capture ulang setelah terbit (file lama dihapus `saveSnapshot`): versi terbit tetap menampilkan peta lama. Ditest di Task 5.
5. Dua user menyimpan draf hampir bersamaan: yang kalah mendapat 409 dan suntingannya tidak menimpa diam-diam. Ditest di Task 1.

---

### Task 1: Skema versi dokumen + model draf

**Files:**
- Create: `database/migrations/2026_11_06_create_renops_dokumen.sql`
- Create: `models/RenopsDokumenModel.php`
- Modify: `models/RenopsModel.php:428-450` (`delete`), `models/OperasiModel.php` (`delete`, blok "7b")
- Modify: `.gitignore` (tambah baris `.superpowers/`)
- Test: `tests/test_renops_dokumen_model.php`

**Interfaces:**
- Produces:
  - `RenopsDokumenModel::getDraft(int $idRenops): ?array` → baris + `konten` (array hasil decode) + `rev`
  - `RenopsDokumenModel::saveDraft(int $idRenops, int $idOperasi, array $sections, int $expectedRev): ?int` → rev baru, atau `null` bila bentrok. Nilai `null` pada kunci menghapus suntingan kunci itu.
  - `RenopsDokumenModel::getLatestTerbit(int $idRenops): ?array`
  - `RenopsDokumenModel::findByToken(string $token): ?array`
  - Semua getter mengembalikan baris tabel ditambah `konten` (hasil `json_decode` `konten_json`, array).
  - `RenopsDokumenModel::statusByOperasi(int $idOperasi): array` → `[id_renops => ['terbit_versi'=>?int]]`
  - `RenopsDokumenModel::collectFilePaths(int $idRenops): list<string>`
  - `RenopsDokumenModel::deleteFilesForRenops(int $idRenops): void`
  - `RenopsDokumenModel::deleteFilesForOperasi(int $idOperasi): void`

- [ ] **Step 1: Tulis migrasi.**
  - Tabel `RenopsDokumen`: `id_dokumen` PK AI, `id_renops` INT NOT NULL FK→`Renops(id_renops)` ON DELETE CASCADE, `id_operasi` INT NOT NULL, `versi` INT NOT NULL DEFAULT 0, `status` VARCHAR(12) NOT NULL, `rev` INT NOT NULL DEFAULT 0, `konten_json` LONGTEXT NOT NULL, `content_hash` CHAR(64) NULL, `token` CHAR(16) NULL, `issued_at` DATETIME NULL, `issued_by` VARCHAR(50) NULL, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.
  - Indeks tabel `RenopsDokumen`: `UNIQUE KEY uq_renopsdok_token (token)`, `KEY idx_renopsdok_status (id_renops, status)`, `KEY idx_renopsdok_ops (id_operasi)`.
  - Tabel `RenopsFoto`: `id_foto` PK AI, `id_renops` FK CASCADE, `path` VARCHAR(255) NOT NULL, `keterangan` VARCHAR(255) NOT NULL DEFAULT '', `urutan` INT NOT NULL DEFAULT 0, `uploaded_by` VARCHAR(50) NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP.
  - Pakai `CREATE TABLE IF NOT EXISTS`, InnoDB, utf8mb4_unicode_ci, seperti migrasi lain.

- [ ] **Step 2: Jalankan migrasi.** `DB_DATABASE=qrsar_test php database/migrate.php`. Output harus memuat nama file migrasi baru tanpa error.

- [ ] **Step 3: Tulis test yang gagal**, `tests/test_renops_dokumen_model.php`.
  - Buat operasi dan RENOPS fixture lewat `OperasiModel::create` / `RenopsModel::create`. Ikuti fixture di `tests/test_renops_lapbang_phase2_3.php`.

```php
$rev1 = RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => 'Teks A'], 0);
assertCondition($rev1 === 1, 'Draf pertama tersimpan dengan rev 1');
$d = RenopsDokumenModel::getDraft($idR);
assertCondition($d['konten']['sections']['mission'] === 'Teks A' && $d['status'] === 'DRAF' && (int)$d['versi'] === 0, 'Draf memuat suntingan');
assertCondition(RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => 'Teks B'], 0) === null, 'Rev usang ditolak (bentrok)');
assertCondition(RenopsDokumenModel::getDraft($idR)['konten']['sections']['mission'] === 'Teks A', 'Suntingan pemenang tidak tertimpa');
$rev2 = RenopsDokumenModel::saveDraft($idR, $idOps, ['awareness' => 'X'], $rev1);
assertCondition($rev2 === 2 && isset(RenopsDokumenModel::getDraft($idR)['konten']['sections']['mission']), 'Simpan menggabungkan kunci');
RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => null], $rev2);
assertCondition(!array_key_exists('mission', RenopsDokumenModel::getDraft($idR)['konten']['sections']), 'Nilai null = Reset kunci');
RenopsModel::delete($idR);
assertCondition(RenopsDokumenModel::getDraft($idR) === null, 'Hapus RENOPS ikut menghapus dokumen (cascade)');
```

- [ ] **Step 4: Jalankan test.** `DB_DATABASE=qrsar_test php tests/test_renops_dokumen_model.php`. Harus FAIL dengan pesan "Class RenopsDokumenModel not found".

- [ ] **Step 5: Implementasikan `models/RenopsDokumenModel.php`.**
  - `saveDraft` memakai `UPDATE … SET rev = rev + 1 … WHERE id_renops=? AND status='DRAF' AND rev=?`. Bila 0 baris berubah dan draf belum ada, `INSERT` hanya diizinkan untuk `$expectedRev === 0`.
  - `collectFilePaths` mengumpulkan `peta.path` dan `foto[].path` dari semua `konten_json` TERBIT/DIGANTIKAN, ditambah path `RenopsFoto`.
  - `deleteFilesForRenops` meng-unlink path yang diawali `uploads/renops_dokumen/` atau `uploads/renops_foto/`. Path lain diabaikan.
  - Panggil `deleteFilesForRenops` di `RenopsModel::delete` **sebelum** `DELETE FROM Renops`. Panggil `deleteFilesForOperasi` di `OperasiModel::delete` di samping `DokumenPetaSnapshotModel::deleteByOperasi`.

- [ ] **Step 6: Jalankan test.** Test yang sama harus PASS semua. Jalankan juga `DB_DATABASE=qrsar_test php tests/test_audit_modul_4_delete_cascade.php`, harus PASS.

---

### Task 2: `terbilang` + resolusi sumber hierarki per hari

**Files:**
- Modify: `helpers/TemplateHelper.php` (tambah method), `helpers/HierarchyHelper.php` (tambah method)
- Test: `tests/test_renops_hierarki_sumber.php`

**Interfaces:**
- Produces:
  - `TemplateHelper::terbilang(int $n): string`, contoh: `1→"satu"`, `8→"delapan"`, `12→"dua belas"`, `21→"dua puluh satu"`, `100→"seratus"`, `1250→"seribu dua ratus lima puluh"`.
  - `HierarchyHelper::absoluteDayNumber(string $hariKe): int`: `"H.3"→3`, `"H7 + 1"→8`. Berbeda dari `extractDayNumber`, yang mengembalikan 1 untuk `"H7 + 1"`. Dipakai untuk mengurutkan pendahulu lintas reopen.
  - `HierarchyHelper::resolveRenopsSources(array $ops, array $renops): array`, dengan bentuk:
    ```php
    ['hari_num'=>int, 'hari_label'=>string,
     'sumber'=>'LAPORAN_AWAL'|'LAPBANG'|'RENOPS',
     'lapbang'=>?array,
     'unsur'=>list, 'alut'=>list, 'cuaca'=>string]
    ```
    `$ops` = `OperasiModel::getById`, `$renops` = `RenopsModel::getById`.

- [ ] **Step 1: Tulis test yang gagal.**
  - Fixture: operasi dengan 2 unsur di Laporan Awal; LAPBANG H1 dengan `detail_unsur` 3 item; RENOPS H1 tanpa unsur; RENOPS H2 dengan 4 unsur; RENOPS H3 tanpa LAPBANG H2.
  - Tanggal dibuat berurutan dari `waktu_berangkat`. Hari ditentukan oleh `TemplateHelper::hitungHariKe`.

```php
assertCondition(TemplateHelper::terbilang(8) === 'delapan' && TemplateHelper::terbilang(21) === 'dua puluh satu' && TemplateHelper::terbilang(1250) === 'seribu dua ratus lima puluh', 'terbilang');
$s1 = HierarchyHelper::resolveRenopsSources($ops, $renopsH1);
assertCondition($s1['hari_num'] === 1 && $s1['sumber'] === 'LAPORAN_AWAL' && count($s1['unsur']) === 2, 'H1: Laporan Awal, RENOPS H1 tanpa unsur tidak menimpa');
$s2 = HierarchyHelper::resolveRenopsSources($ops, $renopsH2);
assertCondition($s2['sumber'] === 'LAPBANG' && (int)$s2['lapbang']['id_laporan'] === $idLapH1, 'H2: sumber LAPBANG H1');
assertCondition(count($s2['unsur']) === 4, 'H2: RENOPS H2 (4 unsur) menang atas LAPBANG H1 (3 unsur)');
$s3 = HierarchyHelper::resolveRenopsSources($ops, $renopsH3);
assertCondition($s3['sumber'] === 'LAPBANG' && (int)$s3['lapbang']['id_laporan'] === $idLapH1, 'H3 tanpa LAPBANG H2: pakai LAPBANG terdekat sebelumnya');
assertCondition(count($s3['unsur']) === 3, 'H3 (RENOPS tanpa unsur) mewarisi 3 unsur LAPBANG terdekat');
assertCondition(HierarchyHelper::absoluteDayNumber('H7 + 1') === 8 && HierarchyHelper::absoluteDayNumber('H.3') === 3, 'Hari absolut');
```

  - Tambah kasus reopen (Review Focus 3):
    - Operasi dengan `waktu_reopen` + `hari_sebelum_reopen = 7`, dan RENOPS bertanggal hari pertama setelah reopen. `hitungHariKe` mengembalikan `"H7 + 1"`.
    - Assert `hari_label === '7 + 1'` dan `hari_num === 8`.
    - Assert `lapbang` adalah LAPBANG terakhir sebelum reopen.

- [ ] **Step 2: Jalankan test.** Harus FAIL dengan "Call to undefined method".

- [ ] **Step 3: Implementasikan `TemplateHelper::terbilang` dan `HierarchyHelper::resolveRenopsSources`.**
  - Hari RENOPS = `absoluteDayNumber(TemplateHelper::hitungHariKe($ops, $renops['tanggal_laporan'], $renops['hari_ke']))`.
  - `hari_label` = string itu tanpa awalan `H.`/`H`, yaitu `"1"` atau `"7 + 1"`.
  - Hari tiap LAPBANG dihitung dari `waktu_laporan` dengan cara yang sama.
  - Urutan pencarian pendahulu untuk N ≥ 2:
    1. LAPBANG hari N-1.
    2. LAPBANG hari < N yang terbesar.
    3. RENOPS hari < N yang terbesar.
    4. Laporan Awal.
  - `unsur`/`alut`/`cuaca`: nilai non-kosong milik RENOPS hari N menimpa pendahulu.

- [ ] **Step 4: Jalankan test.** Harus PASS. Jalankan juga `tests/test_renops_lapbang_phase2_3.php` (regresi `HierarchyHelper`), harus PASS.

---

### Task 3: Resolver dokumen (`RenopsDokumenService::resolve`)

**Files:**
- Create: `services/RenopsDokumenService.php`
- Test: `tests/test_renops_dokumen_resolve.php`

**Interfaces:**
- Consumes: Task 1 (`getDraft`), Task 2 (`resolveRenopsSources`, `terbilang`), `RenopsFotoModel::listByRenops` (Task 4). Bila Task 4 belum ada, panggil dengan guard `class_exists`.
- Produces:
  - `RenopsDokumenService::resolve(int $idRenops): ?array`, mengikuti **Kontrak Data Dokumen**.
  - `RenopsDokumenService::splitAlut(list $alut): array{sarana:list<string>, peralatan:list<string>}`
  - `RenopsDokumenService::grupAset(string $nama): string`
  - `RenopsDokumenService::defaultSections(array $ops, array $renops, array $sumber): array`

- [ ] **Step 1: Tulis test yang gagal.** Fixture: operasi berisi data lampiran:
  - Pelapor `Bapak Syahrir`, hubungan `Kepala Kepolisian Sektor Sampabalo`.
  - Korban `Nurdin`, L, 31, status `DP`.
  - `jumlah_pob` 1, `jenis_kejadian` `Kondisi Membahayakan Manusia`.
  - RENOPS H1 dengan 1 SRU (`nama_sru` `Keluarga dan Warga Setempat`, pola `Circle`) dan alut `Rescue Car` 2 Unit + `Drone` 1 Unit.

```php
$doc = RenopsDokumenService::resolve($idR);
$s = $doc['sections'];
assertCondition(str_contains($s['awareness'], 'Bapak Syahrir') && str_contains($s['awareness'], 'Kondisi Membahayakan Manusia'), 'Awareness dirangkai dari Laporan Awal');
$rows = array_column($s['data_kejadian'], 'value', 'label');
assertCondition(array_keys($rows) === ['Jenis Musibah','Lokasi Duga Kejadian','Waktu Kejadian','Penyebab','Objek yang Dicari','Upaya yang Telah Dilakukan','Jumlah Korban dalam Pencarian','Catatan'], 'Label Tabel 1 sesuai lampiran');
assertCondition($rows['Penyebab'] === 'Hilang' && $rows['Objek yang Dicari'] === '1 (satu) orang' && str_contains($rows['Catatan'], 'Nurdin'), 'Penyebab/objek/identitas korban');
assertCondition($s['sarana'] === ['Rescue Car'] && $s['peralatan'] === ['Drone'], 'Sarana vs Peralatan dari kata kunci');
assertCondition($s['aset'] === [['grup' => 'Darat', 'aset' => 'Rescue Car', 'jumlah' => '2 (dua) unit']], 'Tabel aset hanya Sarana, kolom Aset|Jumlah');
assertCondition($s['sru'][0]['tugas'] === 'Pencarian, pertolongan, dan evakuasi' && $s['sru'][0]['unsur'] === 'Keluarga dan Warga Setempat', 'Tugas SRU default');
assertCondition($s['struktur']['smc'] === $defaultSmcNama && $s['struktur']['sru'][0]['nama'] === 'Keluarga dan Warga Setempat', 'Struktur: SMC default + SRU otomatis');
assertCondition($doc['edited'] === [], 'Tanpa draf: semua Otomatis');
RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => 'Misi sunting'], 0);
$doc2 = RenopsDokumenService::resolve($idR);
assertCondition($doc2['sections']['mission'] === 'Misi sunting' && $doc2['edited'] === ['mission'], 'Suntingan menimpa & tercatat di edited');
```

  - Tambah kasus Review Focus 2: operasi tanpa korban, SRU, alut, maupun snapshot. Assert `resolve()` tidak melempar error dan tidak menghasilkan warning (pakai `set_error_handler` yang menandai gagal). Assert juga `$doc['peta']['path'] === null` dan `area_pencarian` berisi kalimat default.
  - Tambah kasus struktur H2: buat draf H1 dengan `struktur.osc = 'Haeruddin, S.H.'`, lalu `resolve(H2)` → `osc === 'Haeruddin, S.H.'`.

- [ ] **Step 2: Jalankan test.** Harus FAIL ("Class RenopsDokumenService not found").

- [ ] **Step 3: Implementasikan `resolve`, `defaultSections`, `splitAlut`, `grupAset`.**
  - Teks default per bagian diambil dari teks lampiran:
    - `persiapan_personel`: `['Menyiapkan rescuer yang sedang siaga;','Menghubungi rescuer yang berstatus on call; dan','Menyiapkan peralatan.']`
    - `koordinasi` (H1): `['Menyampaikan laporan musibah kepada BCC.','Berkoordinasi dengan:']`, dengan `koordinasi_instansi = ['BCC', …nama unsur hasil hierarki]`.
    - `rescue_plan`: `Korban yang ditemukan dievakuasi ke puskesmas terdekat dan diserahterimakan kepada keluarga korban.`
    - `briefing`: `Briefing dan debriefing disampaikan secara langsung serta melalui radio VHF dan telepon.`
    - `administrasi`: `['Buku jurnal catatan pergerakan unsur;','Catatan data unsur yang terlibat;','Data personel yang terlibat;','Surat Perintah Tugas; dan','Dokumen pendukung lainnya.']`
    - `logistik`: `['Makanan darurat;','Bahan bakar;','Air mineral untuk menjaga daya tahan tubuh; dan','Kebutuhan logistik lainnya.']`
    - `mission`: `Melaksanakan Operasi SAR pada {jenis_kejadian}, yaitu {nama_operasi}.`
    - `waktu`: `Operasi SAR Hari ke-{hari_label} dilaksanakan pada tanggal {formatTanggalIndo(waktu_mulai_ops)}, pukul {formatJamWITA} WITA sampai dengan selesai.`
  - `awareness` memuat: tanggal/jam lapor, `satker_nama`, pelapor (nama, hubungan, telepon), tanggal/jam kejadian, jenis, lokasi, koordinat, dan jarak "dari {SarUnit default `nama_unit`}".
  - `upaya_sebelumnya`:
    - H1: `{satker_nama} telah berkoordinasi dengan BCC, … (instansi) dan keluarga korban.`
    - HN: `isi_laporan` LAPBANG pendahulu.
  - `operation_stage`:
    - H1: `Pada tanggal … pukul … WITA, diberangkatkan {n} ({terbilang}) orang personel {tim_berangkat}.`
    - HN: paragraf hasil LAPBANG pendahulu, lalu kalimat pelaksanaan hari N.
    - `n` = jumlah `jumlah_personil` unsur hasil hierarki.
  - `area_pencarian`:
    - Tanpa SRU: `Melaksanakan koordinasi bersama potensi SAR dan melaksanakan pencarian di sekitar LKP duga.`
    - Dengan SRU: satu kalimat per SRU dari `nama_sru`, `pola_pencarian`, `luas_area`, `koordinat_area`.
  - `peta`: `DokumenPetaSnapshotModel::getSnapshot($idOps,'RENOPS',$idR)`, lalu cadangan `getSnapshot($idOps,'LAPORAN_AWAL')`. Simpan `image_path`.
  - `struktur`:
    - Nilai default disalin dari dokumen RENOPS hari terdekat sebelumnya dalam operasi yang sama, dengan prioritas TERBIT lalu DRAF.
    - Bila tidak ada, salin dari `RenopsDokumen` terbaru di sistem, dengan prioritas TERBIT lalu DRAF.
    - `smc` = `PejabatModel::getDefaultSmc()['nama']` bila ada.
    - `sru` selalu dari SRU hari N.
  - `meta.nomor` = `TemplateHelper::generateNomorRenops($ops, $renops, $settings['satker_kode'] ?? 'SAR-304')`.
  - `meta.closed` = `$ops['status'] === 'CLOSED'`.
  - `kontak` = `SettingsModel::get()` → `no_telp`, `email`, `alamat`.
  - `pengesahan.tanggal` = `tanggal_laporan` RENOPS, `kota` = `'Kendari'`, `smc_*` dari `PejabatModel::getDefaultSmc()` (`nama`, `pangkat`, `nip`).
  - `meta.source_hash` = `hash('sha256', json_encode(sections+peta.path+foto ids))`.

- [ ] **Step 4: Jalankan test.** Harus PASS.

---

### Task 4: Upload foto dokumentasi

**Files:**
- Create: `models/RenopsFotoModel.php`
- Test: `tests/test_renops_foto_upload.php`

**Interfaces:**
- Produces:
  - Konstanta `RenopsFotoModel::MAX_BYTES = 10485760`, `MAX_PER_RENOPS = 12`, `MAX_SIDE = 1600`.
  - `RenopsFotoModel::store(int $idRenops, string $tmpPath, int $size, ?string $userId, string $keterangan = ''): array` → baris. Melempar `InvalidArgumentException` dengan pesan berbahasa Indonesia untuk pengguna.
  - `RenopsFotoModel::listByRenops(int $idRenops): list<array>`, urut `urutan, id_foto`.
  - `RenopsFotoModel::update(int $idFoto, int $idRenops, string $keterangan, int $urutan): bool`
  - `RenopsFotoModel::delete(int $idFoto, int $idRenops): bool`

- [ ] **Step 1: Tulis test yang gagal.** File uji dibuat dengan GD di `sys_get_temp_dir()`:
  - PNG 3000×2000.
  - JPEG ber-EXIF. Pakai JPEG kecil ber-EXIF yang ditulis manual, atau lewati assert EXIF bila `exif_read_data` tidak tersedia.
  - File teks `<?php echo 1;` bernama `.jpg`.

```php
$row = RenopsFotoModel::store($idR, $png, filesize($png), null, 'Persiapan tim');
[$w, $h, $t] = getimagesize(ROOT_DIR . '/' . $row['path']);
assertCondition($t === IMAGETYPE_JPEG && max($w, $h) === 1600, 'Di-encode ulang ke JPEG sisi terpanjang 1600');
assertCondition((bool)preg_match('#^uploads/renops_foto/[0-9a-f]{32}\.jpg$#', $row['path']), 'Nama acak di uploads/renops_foto');
$threw = false; try { RenopsFotoModel::store($idR, $phpFake, filesize($phpFake), null); } catch (InvalidArgumentException) { $threw = true; }
assertCondition($threw, 'Berkas bukan gambar ditolak walau berekstensi .jpg');
$threw = false; try { RenopsFotoModel::store($idR, $png, RenopsFotoModel::MAX_BYTES + 1, null); } catch (InvalidArgumentException) { $threw = true; }
assertCondition($threw, 'Lebih dari 10 MB ditolak');
// isi sampai 12, foto ke-13 ditolak
assertCondition(RenopsFotoModel::delete((int)$row['id_foto'], $idR) && !is_file(ROOT_DIR . '/' . $row['path']), 'Hapus foto meng-unlink berkas');
```

- [ ] **Step 2: Jalankan test.** Harus FAIL.

- [ ] **Step 3: Implementasikan `RenopsFotoModel`.**
  - Gunakan `finfo(FILEINFO_MIME_TYPE)`, `imagecreatefromstring`, lalu `imagescale` bila sisi terpanjang lebih dari 1600, lalu `imagejpeg($img, $dest, 82)`.
  - Buat folder dengan `0755`. Tambahkan `index.php` 403 di folder baru, mengikuti `uploads/index.php`.
  - `urutan` baru = max+1.

- [ ] **Step 4: Jalankan test.** Harus PASS.

---

### Task 5: Terbitkan, bekukan, token, cari token

**Files:**
- Modify: `services/RenopsDokumenService.php`
- Test: `tests/test_renops_dokumen_publish.php`

**Interfaces:**
- Consumes: Task 1, 3, 4.
- Produces:
  - `RenopsDokumenService::publish(int $idRenops, ?string $userId, int $expectedRev): ?array` → `['versi'=>int,'token'=>string,'verify_url'=>string,'public_url'=>string]`, atau `null` bila `rev` draf bukan `$expectedRev`.
  - `RenopsDokumenService::verifyUrl(array $versi): string` → `BASE_URL/operasi.php?action=verify&type=renops&id={id_operasi}&ref={id_renops}&token={token}`
  - `RenopsDokumenService::publicUrl(string $token): string` → `BASE_URL/operasi.php?action=renops_publik&token={token}`
  - `RenopsDokumenService::hasUnpublishedChanges(int $idRenops): bool` → `resolve()['meta']['source_hash']` ≠ `source_hash` versi TERBIT terakhir.

- [ ] **Step 1: Tulis test yang gagal.**

```php
$p1 = RenopsDokumenService::publish($idR, null, 0);
assertCondition($p1['versi'] === 1 && (bool)preg_match('/^[0-9A-F]{16}$/', $p1['token']), 'Terbit v1 dengan token 16 hex');
$v1 = RenopsDokumenModel::findByToken($p1['token']);
assertCondition($v1['status'] === 'TERBIT' && $v1['content_hash'] === hash('sha256', $v1['konten_json']), 'Versi TERBIT + hash isi');
getDB()->prepare("UPDATE Korban SET status_korban='S' WHERE id_operasi=?")->execute([$idOps]);
assertCondition(str_contains(json_encode(RenopsDokumenModel::findByToken($p1['token'])['konten']), 'Hilang'), 'Beku: status korban baru tidak mengubah versi terbit');
$petaV1 = $v1['konten']['peta']['path'];
DokumenPetaSnapshotModel::saveSnapshot([... capture ulang RENOPS $idR ...]);
assertCondition($petaV1 !== null && is_file(ROOT_DIR . '/' . $petaV1) && str_starts_with($petaV1, 'uploads/renops_dokumen/'), 'Peta beku tetap ada setelah capture ulang');
$p2 = RenopsDokumenService::publish($idR, null, (int)RenopsDokumenModel::getDraft($idR)['rev']);
assertCondition(RenopsDokumenModel::findByToken($p1['token'])['status'] === 'DIGANTIKAN' && RenopsDokumenModel::findByToken($p2['token'])['status'] === 'TERBIT' && $p2['versi'] === 2, 'Revisi: v1 DIGANTIKAN, v2 TERBIT');
assertCondition(RenopsDokumenService::publish($idR, null, 999) === null, 'Rev draf usang tidak bisa terbit');
$files = RenopsDokumenModel::collectFilePaths($idR);
RenopsModel::delete($idR);
assertCondition(RenopsDokumenModel::findByToken($p2['token']) === null && !array_filter($files, fn($f) => is_file(ROOT_DIR . '/' . $f)), 'Hapus RENOPS: token mati & berkas beku terhapus');
```

  - Fixture capture ulang: panggil `DokumenPetaSnapshotModel::saveSnapshot` dengan PNG base64 kecil. Ikuti `tests/test_document_map_snapshot.php`.
  - Bila draf belum ada dan `$expectedRev === 0`, `publish` membuat baris DRAF kosong (`{"sections":{}}`, rev 0) lebih dulu. `publish` tidak menaikkan `rev`, sehingga `publish` kedua di test memakai `getDraft($idR)['rev']` (= 0).

- [ ] **Step 2: Jalankan test.** Harus FAIL.

- [ ] **Step 3: Implementasikan `publish`.** Seluruhnya dalam satu transaksi, dengan `SELECT … FOR UPDATE` pada baris DRAF:
  1. Cek `rev`.
  2. `resolve()`.
  3. Salin `peta.path` dan setiap `foto[].path` ke `uploads/renops_dokumen/<hex32>.<ext>`, lalu ganti path di struktur.
  4. `konten_json = json_encode($doc, JSON_UNESCAPED_UNICODE)`, `content_hash = sha256(konten_json)`.
  5. `versi` = max+1.
  6. Buat token sesuai Global Constraints.
  7. Ubah `UPDATE status='DIGANTIKAN'` versi TERBIT lama.
  8. `INSERT` baris TERBIT dengan `issued_at=NOW()`, `issued_by`.

  Bila gagal: rollback dan unlink salinan yang sudah dibuat.

- [ ] **Step 4: Jalankan test.** Harus PASS. Jalankan juga Task 1 dan Task 3, harus PASS.

---

### Task 6: Renderer dokumen + halaman cetak + PDF landscape

**Files:**
- Create: `views/operasi/partials/renops_smeac_doc.php` (renderer tunggal)
- Create: `views/operasi/partials/renops_jaring_komunikasi.php`, `views/operasi/partials/renops_struktur_org.php`
- Create: `views/operasi/renops_smeac_print.php` (halaman cetak/publik mandiri)
- Create: `assets/images/logo_sar_nasional.jpg`. Salin dari `word/media/bd5730a7da8bc592bad689eae7c75c82d7be5ea1.jpg` di `/Users/irfandydk/Downloads/Rencana_Operasi_SAR_Hari_ke-1_KMM_Desa_Manuru.docx` dengan `unzip -p`.
- Modify: `assets/js/pdf_document_exporter.js:120-140, 531-535`
- Test: `tests/test_renops_smeac_render.php`

**Interfaces:**
- Consumes: Kontrak Data Dokumen (fixture array dibuat langsung di test, tanpa DB).
- Produces:
  - Partial `renops_smeac_doc.php` memakai variabel `$doc` (kontrak), `$mode` (`'draft'|'final'`), `$qrUrl` (`?string`, wajib untuk `'final'`), `$versiLabel` (`string`, mis. `"v2"`).
    - Mengeluarkan `<article class="page-sheet renops-smeac" data-pdf-orientation="landscape">`.
    - Pada mode `draft`, tiap bagian berupa elemen `data-sec="<kunci>"` + `data-kind="text|list|table|struktur"` + lencana `Otomatis`/`Disunting` (dari `$doc['edited']`), dan sel tabel ber-`data-col="<nama kolom>"`.
  - Halaman `renops_smeac_print.php` memakai `$doc`, `$qrUrl`, `$versiLabel`, `$isPublic` (`bool`), `$backUrl` (`?string`). Toolbar berisi Cetak dan PDF Gambar (`#btnDownloadPdf`), serta auto-download bila `download_pdf=1` (sudah ditangani exporter).

- [ ] **Step 1: Tulis test yang gagal.** Render dengan `ob_start(); include …; $html = ob_get_clean();` memakai fixture kontrak penuh.

```php
foreach (['SITUATION (SITUASI)','Awareness Stage (Tahap Menyadari)','Initial Action Stage (Tahap Tindakan Awal)','MISSION (TUGAS)','IMPLEMENTATION (PELAKSANAAN)','Planning Stage (Tahap Perencanaan)','OPERATION STAGE (TAHAP OPERASI)','ADMINISTRATION AND LOGISTICS (ADMINISTRASI DAN LOGISTIK)','KOORDINASI, PENGENDALIAN, DAN KOMUNIKASI','Jaring Komunikasi','Struktur Organisasi Operasi SAR'] as $h) {
    assertCondition(str_contains($html, $h), "Judul bab: {$h}");
}
assertCondition(str_contains($html, 'RENCANA OPERASI SAR HARI KE-1') && str_contains($html, 'logo_sar_nasional.jpg'), 'Kop + logo kanan');
assertCondition(!str_contains($html, 'Total Endurance'), 'Tanpa kolom Total Endurance');
assertCondition(str_contains($html, 'Tabel 1. Data Kejadian') && str_contains($html, 'Gambar 1. Peta Lokasi'), 'Penomoran tabel/gambar');
$doc['sections']['mission'] = '<script>alert(1)</script>';
// render ulang mode final
assertCondition(!str_contains($html2, '<script>alert(1)</script>') && str_contains($html2, '&lt;script&gt;'), 'Suntingan di-escape (Review Focus 1)');
assertCondition(str_contains($htmlDraft, 'data-sec="mission"') && str_contains($htmlDraft, 'DRAF') && !str_contains($htmlDraft, 'qrCanvas'), 'Mode draf: atribut sunting, watermark, tanpa QR');
assertCondition(str_contains($htmlFinal, 'qrCanvas') && str_contains($htmlFinal, 'Koordinator Misi Pencarian dan Pertolongan (SMC)'), 'Mode final: blok QR SMC');
// fixture kosong (tanpa peta/foto/sru/aset) dirender tanpa warning
```

- [ ] **Step 2: Jalankan test.** Harus FAIL (file partial belum ada).

- [ ] **Step 3: Implementasikan partial dan halaman.**
  - Tata letak mengikuti lampiran (lihat spec bagian 5):
    - Kop tabel 3 kolom: logo app (`getAppLogoDataUri()`) | judul 3 baris | `assets/images/logo_sar_nasional.jpg`.
    - Penomoran bab `1.`/`a.`/`1)`.
    - Tabel `table-dinas` dengan `thead` berulang. Baris tidak terpotong (`tr { break-inside: avoid }`).
    - Grid foto 2 kolom, keterangan `Gambar {n}. {keterangan}`.
    - Blok pengesahan rata kanan: `{kota}, {tanggal Indo}` / `Koordinator Misi Pencarian dan Pertolongan (SMC)` / QR 28 mm / nama / pangkat.
  - `@page { size: A4 landscape; margin: 20mm 20mm 18mm 20mm; @top-center { content: "<header>" } @bottom-center { content: "Halaman " counter(page) " dari " counter(pages) } }`.
  - Diagram jaring komunikasi dibuat sebagai SVG inline meniru gambar lampiran:
    - 5 kotak: Basarnas Pusat, Kantor (oranye), Instansi Pemerintah, Potensi SAR, Pos dan Unit.
    - Garis tegas = pengendalian, putus-putus = koordinasi, plus legenda.
    - Teks Kantor diambil dari `$doc['kontak']`.
    - Kotak lain berisi teks statis lampiran (Telepon 021-65867510 / 65867511, Faksimile 021-65867512, Emergency Call 115, dst.).
  - Bagan struktur organisasi dibuat dengan HTML grid/SVG mengikuti lampiran: SC → SMC → OSC → SRU…, ditambah 6 kotak staf di kanan.
  - Pindahkan skrip QR berlogo dari `views/operasi/renops_print.php:551-623` ke halaman baru. Canvas id `qrCanvas`.
  - Exporter: baca `sheet.dataset.pdfOrientation`. Untuk `landscape`, tukar `pageWidthMm`/`pageHeightMm` (297×210) dan margin, lalu `jsPDF.orientation = 'landscape'`. Perilaku portrait tidak berubah.

- [ ] **Step 4: Jalankan test.** Harus PASS. Jalankan juga `tests/test_audit_modul_5_verify_qr_pdf.php`, yang mengecek exporter, untuk memastikan portrait tidak rusak.

---

### Task 7: API dokumen & foto

**Files:**
- Create: `api/controllers/RenopsDokumenApiController.php`
- Modify: `api/index.php:89-92`. Sebelum `RenopsApiController`, tambahkan: bila `preg_match('#^/renops/\d+/(dokumen|foto)#', $path)`, panggil `RenopsDokumenApiController::handle`.
- Test: `tests/test_renops_dokumen_api.php` (bagian HTTP hanya jalan dengan `QRSAR_TEST_URL`)

**Interfaces:**
- Consumes: Task 1, 3, 4, 5.
- Produces (semua `requireAuth()`; 404 bila RENOPS tidak ada; **tanpa** cek CLOSED):
  - `GET /renops/{id}/dokumen` → `{doc, rev, terbit_versi:?int, has_unpublished:bool, closed:bool, foto:[…]}`
  - `PUT /renops/{id}/dokumen` body `{sections:{…}, rev:int}` → `{rev}`, atau 409 `{message:"Draf telah diubah pengguna lain. Muat ulang halaman."}`
  - `POST /renops/{id}/dokumen/terbit` body `{rev:int}` → `{versi, token, verify_url, public_url, print_url}`, atau 409
  - `POST /renops/{id}/foto` multipart `foto[]` (+ `keterangan[]` opsional) → `{foto:[…]}`. 422 dengan pesan dari `InvalidArgumentException`. Wajib `is_uploaded_file`.
  - `PUT /renops/{id}/foto/{idFoto}` `{keterangan, urutan}` → `{success:true}`
  - `DELETE /renops/{id}/foto/{idFoto}` → `{success:true}`
  - `sections` divalidasi: hanya kunci yang ada di kontrak; nilai string, list string, atau list objek string. String dipotong maksimal 20 000 karakter. Kunci tak dikenal → 422.

- [ ] **Step 1: Tulis test yang gagal.**
  - Bagian non-HTTP: assert `RenopsDokumenApiController::sanitizeSections(['mission'=>'ok','hacker'=>'x'])` melempar `InvalidArgumentException`, dan `sanitizeSections(['sru'=>[['unsur'=>'A','tugas'=>'B','area'=>'','pola'=>'','track_spacing'=>'']]])` lolos.
  - Bagian HTTP (`QRSAR_TEST_URL`): `GET /api/renops/{id}/dokumen` tanpa login → 401; `PUT` tanpa CSRF → 403.

- [ ] **Step 2: Jalankan test.** Harus FAIL.

- [ ] **Step 3: Implementasikan controller.** Pola mengikuti `RenopsApiController` (`self::json`, `self::error`). `sanitizeSections` dibuat `public static` agar bisa dites.

- [ ] **Step 4: Jalankan test.**
  - Non-HTTP: `DB_DATABASE=qrsar_test php tests/test_renops_dokumen_api.php` harus PASS.
  - HTTP: jalankan juga dengan `QRSAR_TEST_URL=http://localhost:8091` bila server test tersedia.

---

### Task 8: Halaman editor draf (Opsi A)

**Files:**
- Create: `views/operasi/renops_dokumen.php`
- Create: `assets/js/pages/renops_dokumen.js`
- Modify: `controllers/OperasiController.php` (aksi `renops_dokumen`, login)
- Test: `tests/test_renops_dokumen_editor.php` (statis: view & JS)

**Interfaces:**
- Consumes:
  - Partial Task 6 (`$mode='draft'`) dan API Task 7.
  - Mockup acuan: `.superpowers/brainstorm/30065-1793928409/content/editor-layout.html` opsi A.
- Produces: URL `operasi.php?action=renops_dokumen&id_renops={id}`.

- [ ] **Step 1: Tulis test yang gagal.**
  - Assert `views/operasi/renops_dokumen.php` meng-include `partials/renops_smeac_doc.php` dan memuat `assets/js/pages/renops_dokumen.js` via `assetUrl`.
  - Assert ada tombol ber-id `btnSimpanDraf`, `btnUploadFoto`, `btnTerbitkan`, `btnCetak`, `btnCapturePeta`, panel `#renopsToc`, dan peringatan CLOSED (`data-closed-warning`).
  - Assert JS memakai `contenteditable="plaintext-only"` / `innerText` (bukan `innerHTML`) saat mengumpulkan nilai, serta mengirim `rev`.

- [ ] **Step 2: Jalankan test.** Harus FAIL.

- [ ] **Step 3: Implementasikan.**
  - **Server:**
    - Render partial dengan `resolve()` live, `rev` draf, status terbit, dan `hasUnpublishedChanges`.
    - Lencana toolbar: `DRAF` / `TERBIT v{n}` / `Ada perubahan belum diterbitkan`.
    - `btnCetak` nonaktif dengan title `Terbitkan dulu` bila belum ada versi TERBIT. Bila sudah ada, membuka `print_renops`.
    - `btnCapturePeta` membuka `gis.php?id_operasi={id}&include_base=1&capture_doc=RENOPS&capture_ref={id_renops}`.
  - **JS:**
    - Tandai bagian kotor (dirty) saat `input`.
    - Simpan mengirim `PUT` hanya bagian kotor. Nilai dikumpulkan per `data-kind`:
      - `text` → `innerText`
      - `list` → `li` per butir
      - `table` → baris per `data-col`
      - `struktur` → field per jabatan
    - Tambah/hapus baris tabel dan butir daftar.
    - Reset mengirim `PUT {sections:{key:null}}` lalu muat ulang.
    - Upload memakai `<input type=file multiple accept="image/jpeg,image/png,image/webp">` → `POST foto` → muat ulang.
    - Keterangan foto disimpan lewat `PUT foto/{id}` saat blur.
    - Terbitkan: simpan dulu, lalu `POST terbit`, lalu muat ulang. Konfirmasi: "Terbitkan versi baru? QR versi sebelumnya akan berstatus 'telah diubah'."
    - Respons 409 → alert pesan server.
  - CSRF otomatis lewat wrapper `fetch` di `assets/js/app.js`.

- [ ] **Step 4: Jalankan test dan cek di browser.**
  - Test harus PASS.
  - Lalu verifikasi di browser:
    1. Buka editor untuk RENOPS uji.
    2. Sunting Mission lalu simpan. Lencana berubah menjadi Disunting.
    3. Upload 2 foto. Grid muncul.
    4. Terbitkan. Status menjadi TERBIT v1.
    5. Ubah lagi. Muncul lencana "Ada perubahan belum diterbitkan".

---

### Task 9: Rute cetak, publik & verifikasi + integrasi tab

**Files:**
- Modify: `controllers/OperasiController.php`:
  - `print_renops` di :179-182
  - aksi baru `renops_publik` sebelum gerbang login `print_` di :167-170
  - cabang renops di `verifyDocument` :214-274
- Modify: `views/operasi/verify_public.php` (variabel opsional `$renopsVerify`)
- Modify: `views/operasi/partials/tab_cetak.php:63-130`, `views/operasi/partials/tab_renops.php:103-110`
- Delete: `views/operasi/renops_print.php`
- Modify (test lama yang mengacu `renops_print.php`): `tests/test_audit_modul_5_verify_qr_pdf.php`, `tests/test_audit_optimalisasi_menyeluruh.php`, `tests/test_buttons_and_navigation_e2e.php`, `tests/test_document_map_snapshot.php`, `tests/test_doc_verify_and_barcode.php`, `tests/test_renops_lapbang_phase2_3.php`, `tests/test_renops_distance_unit_selection.php`
- Test: `tests/test_renops_dokumen_verify.php`

**Interfaces:**
- Consumes: Task 5 (`findByToken`, `getLatestTerbit`, `publicUrl`, `verifyUrl`), Task 6 (`renops_smeac_print.php`), Task 1 (`statusByOperasi`).
- Produces: `OperasiController::resolveRenopsVerification(string $token, int $idOps, int $refId): ?array` (public static, dapat dites) → `['status'=>'SAH'|'DIUBAH', 'versi'=>int, 'latest_versi'=>int, 'open_url'=>string, 'issued_at'=>string]`, atau `null` = tidak valid / serahkan ke jalur lama. Aturannya:
  - Token ada di `RenopsDokumen` milik `refId`/`idOps`:
    - TERBIT → `SAH`, `open_url` = `publicUrl(token)`.
    - DIGANTIKAN → `DIUBAH`, `open_url` = `publicUrl(token TERBIT terbaru)`.
  - Token tidak ada → `null`. `verifyDocument` lalu menjalankan `DocVerifyHelper::verifyToken` lama:
    - Sah dan `getLatestTerbit(refId)` ada → `DIUBAH`.
    - Sah tanpa versi terbit → halaman SAH lama (tidak berubah).
    - Tidak sah → `verify_invalid` 404.

- [ ] **Step 1: Tulis test yang gagal.**

```php
$p1 = RenopsDokumenService::publish($idR, null, $rev); $p2 = RenopsDokumenService::publish($idR, null, $rev2);
$v = OperasiController::resolveRenopsVerification($p2['token'], $idOps, $idR);
assertCondition($v['status'] === 'SAH' && str_contains($v['open_url'], $p2['token']), 'Token versi terbaru: SAH');
$v = OperasiController::resolveRenopsVerification($p1['token'], $idOps, $idR);
assertCondition($v['status'] === 'DIUBAH' && $v['latest_versi'] === 2 && str_contains($v['open_url'], $p2['token']), 'Token lama: DIUBAH + tautan versi terbaru');
assertCondition(OperasiController::resolveRenopsVerification($p2['token'], $idOps, $idR + 999) === null, 'Token tidak cocok ref → null');
RenopsModel::delete($idR);
assertCondition(OperasiController::resolveRenopsVerification($p2['token'], $idOps, $idR) === null, 'RENOPS dihapus → tidak valid');
```

  - Bagian HTTP (`QRSAR_TEST_URL`), tanpa login:
    - `GET /operasi.php?action=renops_publik&token={valid}` → 200, header `X-Robots-Tag: noindex, nofollow`, body memuat `MISSION (TUGAS)`.
    - Token acak → 404.
    - `GET /operasi.php?action=print_renops&id_renops={id}` → 302 ke login.

- [ ] **Step 2: Jalankan test.** Harus FAIL.

- [ ] **Step 3: Implementasikan.**
  - **`renops_publik`:**
    - Ambil token, `findByToken`.
    - Tidak ada → render `bra/verify_invalid` (404).
    - Ada → `header('X-Robots-Tag: noindex, nofollow')` lalu render `renops_smeac_print.php` dengan `$doc = konten` versi itu, `$qrUrl = verifyUrl(versi itu)`, `$isPublic = true`, `$backUrl = null`.
    - Halaman juga memuat `<meta name="robots" content="noindex, nofollow">`.
  - **`print_renops`:**
    - Login.
    - `id_renops`, atau RENOPS terakhir operasi bila kosong. Ini mempertahankan `operasi_detail.js:704` dan `gis_master.js:7108`.
    - Ada `getLatestTerbit` → render versi itu (`$isPublic=false`, `$backUrl` ke editor). Tidak ada → redirect ke `renops_dokumen`.
  - **`verifyDocument`, cabang `type=renops`:** gunakan `resolveRenopsVerification` lebih dulu, lalu jalur lama sesuai aturan di atas.
  - **`verify_public.php`:**
    - `$renopsVerify` diset, status `DIUBAH`: banner amber `DOKUMEN TELAH DIUBAH` + teks `Versi v{versi} telah digantikan oleh v{latest_versi}.` + tombol `Klik di sini untuk membuka dokumen` → `open_url`.
    - `$renopsVerify` diset, status `SAH`: tombol `Buka dokumen` → `open_url`, menggantikan `printUrl`.
  - **Tab Cetak & tab RENOPS:**
    - Per RENOPS: tombol `Edit Draf` → `renops_dokumen`, dan `Cetak` / `Unduh PDF` → `print_renops` (+`&download_pdf=1`).
    - Lencana `TERBIT v{n}` atau `Belum terbit` dari `statusByOperasi`.
    - Teks deskripsi kartu RENOPS diganti: `Rencana Operasi SAR format SMEAC (A4 landscape): draf, foto dokumentasi, terbit QR.`
  - Hapus `views/operasi/renops_print.php`. Perbarui test lama: assert yang menunjuk file itu dialihkan ke `renops_smeac_print.php` / `partials/renops_smeac_doc.php`, dan assert khusus format portrait dihapus.

- [ ] **Step 4: Jalankan test.**
  - `DB_DATABASE=qrsar_test php tests/test_renops_dokumen_verify.php` harus PASS.
  - Lalu suite penuh `DB_DATABASE=qrsar_test php tests/run_all.php` harus 0 FAIL. Bila ada test HTTP yang di-skip, laporkan sebagai SKIP.

---

### Task 10: Verifikasi akhir end-to-end

**Files:** tidak ada file baru.

- [ ] **Step 1: Jalankan semua test.**
  - `DB_DATABASE=qrsar_test php tests/run_all.php` harus 0 FAIL.
  - `node tests/js/bra_templates.test.js` harus semua PASS (regresi).
- [ ] **Step 2: Jalankan alur di browser (dev server proyek) pada operasi uji.**
  1. RENOPS H1 → Edit Draf → sunting → upload foto → Terbitkan.
  2. Cetak → pratinjau cetak A4 landscape, header berjalan, dan "Halaman X dari Y".
  3. PDF Gambar landscape.
  4. Pindai URL QR → SAH → Buka dokumen tanpa login (jendela private).
  5. Revisi → QR lama menampilkan "Dokumen telah diubah" + tautan.
  6. Hapus RENOPS (admin) → QR menampilkan tidak valid.
  7. Ulangi langkah 1 dan 2 pada operasi CLOSED (K16).
- [ ] **Step 3: Bandingkan visual dengan lampiran.** Bandingkan hasil cetak berdampingan dengan docx. Catat selisih tata letak yang tersisa untuk pengguna.
