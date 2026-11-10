# Verifikasi Laporan Audit Kode Mati Lanjutan — QR-SAR

**Tanggal:** 10 November 2026
**Yang diverifikasi:** `docs/ponytail-audit-kode-mati-2026-11-10.md`
**Sifat:** analisis statis, baca saja. Tidak ada kode yang dijalankan atau diubah.

## Kesimpulan

Temuan di laporan itu sebagian besar valid: lokasi file, nama fungsi, dan jumlah suite tes (81 PHP, 6 JS) cocok dengan kode saat ini.

Laporan itu **belum aman dieksekusi apa adanya**. Rentang baris di temuan 6 dan cara hapus di temuan 5 akan merusak aplikasi kalau diikuti persis.

## Kondisi kode saat verifikasi

- Working tree sudah berisi pembersihan yang belum di-commit: 20 file, −379 baris.
- Isinya mengerjakan sebagian audit pertama: endpoint admin, AI draft LAPBANG, rute OSRM, fungsi preset, dan beberapa fungsi JS.
- Laporan lanjutan menilai dari kondisi tersebut.

## Penilaian per temuan

| No | Temuan | Valid? | Siap dieksekusi? | Catatan |
|----|--------|--------|------------------|---------|
| 1 | Asersi yatim phase8 & 3 fungsi `GeoHelper` | Ya | Ya, paling dulu | Tes phase8 sekarang gagal |
| 2 | Pembuat naskah BRA PHP lama | Ya | Ya, satu koreksi kecil | Label "Must Fix" berlebihan |
| 3 | `LogService::cleanOldLogs()` tak dipanggil | Fakta benar | Tunggu keputusan pemilik | Prioritas berlebihan |
| 4 | Fungsi JS warisan | Ya | Ya, dengan catatan | Jumlahnya 10 fungsi, bukan 9 |
| 5 | CSS lama pra-Tailwind | Sebagian | **Hanya dengan koreksi** | Ada aturan CSS yang masih dipakai |
| 6 | Template BRA Pergerakan Kapal | Isinya ya | **Rentang baris salah** | Diikuti persis = error 500 |
| 7 | Alias/wrapper PHP | Ya, tapi kurang lengkap | Ya | Ada sekitar 14 alias lain yang terlewat |
| 8 | `views/errors/403.php` | Ya | Ya, sebaiknya hapus | Satu alasan kurang tepat |

---

## Koreksi wajib sebelum eksekusi

### Temuan 6 — rentang baris berbahaya

**`helpers/BraHelper.php:216-375` salah.**

- `buildSectionsFromVesselMovement` berakhir di baris **365**.
- Baris 367–375 adalah docblock dan awal `formatTelegramReduplication`. Fungsi ini dipakai BRA MAPEL lewat `buildSectionsFromMapel`.
- Kalau 216–375 dihapus, PHP gagal membaca file. `BraHelper` tidak termuat, dan halaman BRA error 500.
- **Rentang yang benar: 213–366** (termasuk docblock `getAvailableVessels` di baris 213).

**`tests/test_tahap3_bra_fixes.php:113-170` salah.**

- Blok tes 2.1–2.7 berakhir di baris **172** (`);`).
- Kalau dihapus sampai 170, baris 171 (pesan asersi) dan 172 (`);`) tertinggal. File tes jadi tidak valid.
- **Rentang yang benar: 108–172** (header "BAGIAN 2" di 108–111 ikut dihapus).
- Tes 2.8 (baris 174 dst.) tetap jalan, karena `$formFile` didefinisikan di baris 94, di luar blok yang dihapus.

**`api/controllers/BraApiController.php:50-64`** sudah tepat (blok route 3 dan 4).

### Temuan 5 — `.table-sar` digabung dengan `.table`

- Di `assets/css/style.css:785-812`, ada empat aturan dengan selektor gabungan `.table, .table-sar` (termasuk `th`, `td`, `tbody tr:hover`).
- Kelas `.table` **masih dipakai** di:
  - `views/operasi/sitrep_form.php:275`
  - `views/operasi/partials/tab_renops.php:149`
- Kalau keempat aturan itu dihapus utuh, tabel di form LAPBANG dan tab RENOPS kehilangan gayanya.
- **Perbaikan:** hapus hanya bagian `, .table-sar` dari selektor.
- Catatan laporan untuk mempertahankan `.bra-table-wrapper` dan `.senkom-vertical-label` (baris 1016 dan 1022, di dalam rentang hapus) sudah benar. Kedua kelas itu dipakai `views/bra/form.php`, `views/bra/view.php`, dan diuji `tests/test_bra_perfection.php`.
- Selain empat aturan gabungan tadi, tidak ada aturan lain yang mencampur kelas mati dan kelas hidup.

### Temuan 2 — satu rentang tes kurang

- `tests/test_audit_optimalisasi_menyeluruh.php:223` harus dihapus bersama baris **222** (komentar) dan **224** (asersi).
- Kalau hanya baris 223, baris 224 memakai variabel `$tplLaporanAwal` yang tidak ada, dan tesnya gagal.
- Rentang lain sudah tepat:
  - `helpers/BraHelper.php:517-784` (kurung tutup class di baris 785 tetap; `countBraWords` di 538–555 tetap)
  - `tests/test_tahap2_logic_integrity.php:142-156`
  - `tests/test_bra_mapel.php:175-180`
- Setelah 142–156 dihapus, variabel `$mockSitrep` (sekitar baris 133–141) tidak terpakai lagi. Tidak error, tapi bisa ikut dihapus.

---

## Catatan per temuan lain (tidak merusak)

### Temuan 1

- Benar dan paling mendesak.
- `tests/test_phase8_relations_logger.php:176-179` masih memeriksa `function handleRoutingDirectionsRoute(`, padahal fungsi itu sudah dihapus dari `GisApiController.php`.
- Tes ini keluar dengan `exit(1)` bila ada asersi gagal, jadi suite tidak 81/81.
- Rentang `helpers/GeoHelper.php:90-109` tepat. `calculateDistanceNm`, `calculateDistanceKm`, `calculateBearing` tidak punya pemanggil di kode maupun tes.
- **Sisa yang terlewat:** `api/index.php:123` masih menyebut `$path === '/maps/calculate-route'`. Tidak error (jatuh ke 404), tapi ikut dihapus.

### Temuan 3

- `cleanOldLogs` memang tidak pernah dipanggil.
- Saat ini hanya ada 15 file log, total 360 KB dalam sekitar 3 minggu. Perkiraan: sekitar 6 MB per tahun. Itu bukan "Must Fix".
- Lama penyimpanan log adalah keputusan pemilik aplikasi.
- Kalau dijalankan otomatis, panggil saat admin membuka tab log, jangan setiap kali log ditulis (jalur yang sering dilewati).

### Temuan 4

- Jumlahnya **10 fungsi**, bukan 9: `updateThemeIcon`, `getVerifiedMaritimePresets`, `formatDmsCoordPart`, `loadSarUnits`, `drawSearchPattern`, `countWords`, `calculateDestinationPoint`, `calculateCentroid`, `calculatePatternDistance`, `calculateAreaSwept`.
- Semua rentang baris tepat untuk isi fungsinya, dan tidak ada tes yang memakai fungsi-fungsi itu.
- Rentang hanya mencakup isi fungsi. Komentar di atasnya ikut dihapus supaya tidak tertinggal:
  - `assets/js/gis/geodesy.js` 163–169 dan 300–305
  - `assets/js/utils/common.js` 203–208
  - `assets/js/gis/patterns.js` 491–493
  - `assets/js/gis_master.js` 665
  - `assets/js/gis.js` 311 dan 347
- Alasan mempertahankan `setUnit`, `getUnit`, `updateBadges` keliru: `renops_form.js` punya `RENOPS_FORM.setUnit` sendiri dan tidak memakai yang di `SAR_GIS`. Tapi keputusan mempertahankannya tetap benar, karena dikunci `tests/test_gis_dynamic_units_and_badges.php:227-230`.

### Temuan 7

- Sepuluh alias yang disebut memang tanpa pemanggil dan tanpa tes. Nomor barisnya tepat.
- Klaim bahwa `SettingsModel::exportJsonBackup` "diuji" **salah**. Tes hanya memakai `BackupService::exportJsonBackup` (`test_phase4_refactoring_architecture.php:62`). Sejak endpoint ekspor dihapus, fungsi ini tidak punya pemanggil.
- Alias lain yang juga tanpa pemanggil dan tanpa tes, tapi tidak tercantum:
  - `SettingsModel`: `updatePassword`, `updatePejabat`, `deletePejabat`, `restoreFromJson`, `exportJsonBackup`, `generateSQLDump`, `splitSqlStatements`, `restoreFromSQL`
  - `UserModel::updatePassword`
  - `SarUnitModel::getSarUnits`, `SarUnitModel::getById`
  - `PejabatModel::getPejabat`, `PejabatModel::getById`
  - `OperasiModel::countAll`, `AuthHelper::clearCache`, `TemplateHelper::getNamaHariIndo`
- Yang memang harus tetap (karena diuji): `MasterModel::createSarUnit`, `MasterModel::deleteSarUnit`, `SettingsModel::getDbStats`, `GisModel::exportGeoJson`, `LogService::getLogContent`, `TemplateHelper::formatDMS`, `ViewHelper::resolve`. Pilihannya: biarkan, atau hapus bersama asersi tesnya.

### Temuan 8

- `AuthHelper` tidak mengirim 403; yang mengirim adalah `helpers/Router.php` dan `.htaccess`, dalam bentuk teks polos.
- Saran hapus file tetap paling sederhana. Menyambungkan halaman 403 berdesain berarti menambah fitur, bukan membersihkan.

### Bagian "Tidak dicek" di laporan

- Kalimat bahwa endpoint API Operasi "masih dikonsumsi dashboard" hanya benar untuk `GET /operasi`, `GET /operasi/{id}`, dan `DELETE /operasi/{id}`.
- `POST /operasi`, `/operasi/{id}/close`, `/laporan/stats`, `/monitoring/stats`, `/bra/next-number`, dan endpoint `/master/*` tetap tidak dipanggil UI.
- Tidak memasukkannya ke laporan tidak masalah (pilihan konservatif), tapi alasannya terlalu luas.

---

## Urutan eksekusi yang aman

1. Temuan 1 (termasuk `api/index.php:123`), lalu jalankan suite sampai 81/81 kembali.
2. Temuan 2, 6, 4, 7, 8 dengan koreksi di atas.
3. Temuan 5, hanya bagian `, .table-sar` untuk aturan gabungan.
4. Temuan 3 setelah pemilik menentukan lama penyimpanan log.

## Batas verifikasi

- Semua ini dari membaca kode, bukan menjalankannya.
- Klaim bahwa tes phase8 gagal berdasarkan isi asersinya dan `exit(1)`, bukan hasil menjalankan suite.
