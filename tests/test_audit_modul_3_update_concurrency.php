<?php
/**
 * QR-SAR - Automated Audit & E2E Test Suite: MODUL 3 (Siklus Pembaruan Data & Konkurensi State)
 * KPP Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../helpers/BraHelper.php';
require_once __DIR__ . '/../helpers/TemplateHelper.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/RenopsModel.php';
require_once __DIR__ . '/../models/LaporanModel.php';
require_once __DIR__ . '/../models/BraModel.php';
require_once __DIR__ . '/../models/MasterModel.php';
require_once __DIR__ . '/../models/GisModel.php';

$totalPassed = 0;
$totalFailed = 0;

function report(string $name, bool $passed, string $detail = ''): void {
    global $totalPassed, $totalFailed;
    if ($passed) {
        $totalPassed++;
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
    } else {
        $totalFailed++;
        echo "  [FAIL] {$name}" . ($detail ? " - DETAIL: {$detail}" : "") . "\n";
    }
}

echo "\n=========================================================================\n";
echo "  AUDIT E2E MODUL 3: SIKLUS PEMBARUAN DATA (UPDATE/EDIT) & KONKURANSI STATE \n";
echo "  KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS      \n";
echo "=========================================================================\n\n";

$db = Database::getConnection();
$dbOpsCountInitial = (int)$db->query("SELECT COUNT(*) FROM Operasi")->fetchColumn();

// -------------------------------------------------------------------------
// 1. Setup Baseline Data Operasi untuk Pengujian Pembaruan
// -------------------------------------------------------------------------
echo "--- 1. Setup Baseline Data Operasi ---\n";

$baseData = [
    'nama_operasi' => 'Operasi SAR Uji Modul 3 Pembaruan & State',
    'nama_pelapor' => 'Bapak Hendra (Syahbandar)',
    'hubungan_pelapor' => 'Petugas KSOP',
    'no_telp_pelapor' => '0812-4444-111',
    'waktu_kejadian' => '2026-10-21 07:00:00',
    'waktu_lapor' => '2026-10-21 07:30:00',
    'waktu_berangkat' => '2026-10-21 07:45:00',
    'eta_tiba' => '2026-10-21 09:00:00',
    'lokasi_kejadian' => 'Perairan Teluk Kendari Bagian Timur',
    'koordinat' => "03° 58' 30.00\" S - 122° 36' 15.00\" E",
    'jarak' => '12 NM',
    'radial' => '090° Timur',
    'eta_lokasi' => '45 Menit',
    'kronologi' => 'Perahu nelayan patah as kemudi terombang-ambing di dekat lampu suar.',
    'jumlah_pob' => 3,
    'cuaca' => 'Cerah',
    'gelombang' => '0.5 - 1.0 M',
    'angin' => 'Timur, 5 - 10 Knots',
    'faktor_penghambat' => 'Nihil',
    'tim_berangkat' => 'Rescuer Kansar Kendari',
    'korban' => [
        ['nama_korban' => 'Korban Asli A', 'umur' => '40', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => 'DP'],
        ['nama_korban' => 'Korban Asli B', 'umur' => '35', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => 'DP'],
        ['nama_korban' => 'Korban Asli C (Akan Dihapus)', 'umur' => '20', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => 'DP']
    ],
    'detail_unsur' => [
        ['nama_unsur' => 'Kansar Kendari', 'jumlah_personil' => 5],
        ['nama_unsur' => 'Polairud Polda Sultra', 'jumlah_personil' => 3]
    ],
    'detail_alut' => [
        ['id_alut' => 1, 'nama_alut' => 'RIB 01 Kendari', 'satuan' => 'Unit', 'jumlah_alut' => 1]
    ]
];

$testOpsId = OperasiModel::create($baseData);
report("Pembuatan data baseline Operasi SAR berhasil", $testOpsId > 0, "ID: {$testOpsId}");

// Catat ID Korban Asli untuk pengujian diferensial
$stmtGetK = $db->prepare("SELECT id_korban, nama_korban FROM Korban WHERE id_operasi = :id ORDER BY id_korban ASC");
$stmtGetK->execute([':id' => $testOpsId]);
$initialKorbanRows = $stmtGetK->fetchAll();
$initialKIds = array_column($initialKorbanRows, 'id_korban');
report("Baseline Korban memiliki 3 entitas awal", count($initialKIds) === 3, "KIDs: " . implode(',', $initialKIds));

// -------------------------------------------------------------------------
// 2. Audit Pembaruan Laporan Awal & Algoritma Diferensial Korban/Unsur/Alut
// -------------------------------------------------------------------------
echo "\n--- 2. Audit Algoritma Pembaruan Diferensial (Child Diffing) ---\n";

// Skenario Diffing Korban:
// - Korban A: diubah namanya menjadi 'Korban A (Telah Diedit)' (reused ID)
// - Korban B: tetap
// - Korban C: dihapus
// - Korban D: ditambahkan baru
$firstKid = $initialKIds[0];
$secondKid = $initialKIds[1];
$thirdKid = $initialKIds[2];

$updateOpsData = [
    'nama_operasi' => 'Operasi SAR Uji Modul 3 [JUDUL DIUBAH]',
    'koordinat' => "04° 02' 10.00\" S - 122° 45' 00.00\" E", // Koordinat bergeser
    'lokasi_kejadian' => 'Perairan Toronipa Konawe',
    'jarak' => '18 NM',
    'radial' => '045° Timur Laut',
    'kronologi' => 'Kronologi kejadian telah diperbarui setelah konfirmasi saksi mata di dermaga.',
    'jumlah_pob' => 3,
    'korban' => [
        ['id_korban' => $firstKid, 'nama_korban' => 'Korban A (Telah Diedit)', 'umur' => '41', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => 'DP'],
        ['id_korban' => $secondKid, 'nama_korban' => 'Korban Asli B', 'umur' => '35', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => 'DP'],
        ['nama_korban' => 'Korban Baru D (Baru Masuk)', 'umur' => '28', 'jk' => 'Perempuan', 'alamat' => 'Toronipa', 'status_korban' => 'DP']
    ],
    'detail_unsur' => [
        ['nama_unsur' => 'Kansar Kendari', 'jumlah_personil' => 8], // update personil
        ['nama_unsur' => 'Polairud Polda Sultra', 'jumlah_personil' => 3],
        ['nama_unsur' => 'Pos AL Kendari', 'jumlah_personil' => 4] // unsur baru
    ],
    'detail_alut' => [
        ['id_alut' => 1, 'nama_alut' => 'RIB 01 Kendari', 'satuan' => 'Unit', 'jumlah_alut' => 1],
        ['id_alut' => 2, 'nama_alut' => 'Rubber Boat 02', 'satuan' => 'Unit', 'jumlah_alut' => 1] // alut baru
    ]
];

$updateResult = OperasiModel::update($testOpsId, $updateOpsData);
report("OperasiModel::update berhasil dieksekusi", $updateResult === true);

// Verifikasi judul dan koordinat Laporan Awal
$stmtUpdatedOps = $db->prepare("SELECT nama_operasi, koordinat, lokasi_kejadian, status FROM Operasi WHERE id_operasi = :id");
$stmtUpdatedOps->execute([':id' => $testOpsId]);
$rowUpdatedOps = $stmtUpdatedOps->fetch();
report("Judul operasi berhasil diperbarui", $rowUpdatedOps['nama_operasi'] === 'Operasi SAR Uji Modul 3 [JUDUL DIUBAH]');
report("Koordinat lokasi kejadian berhasil diperbarui", $rowUpdatedOps['koordinat'] === "04° 02' 10.00\" S - 122° 45' 00.00\" E");

// Verifikasi algoritma diferensial Korban
$stmtAfterK = $db->prepare("SELECT id_korban, nama_korban FROM Korban WHERE id_operasi = :id ORDER BY id_korban ASC");
$stmtAfterK->execute([':id' => $testOpsId]);
$afterKorbanRows = $stmtAfterK->fetchAll();
$afterKIds = array_column($afterKorbanRows, 'id_korban');

report("Jumlah korban setelah diffing tetap 3", count($afterKIds) === 3, "Count: " . count($afterKIds));
report("ID Korban pertama ($firstKid) di-reuse tanpa membuat ID melonjak", in_array($firstKid, $afterKIds, true));
report("Nama Korban pertama berhasil diperbarui di database", $afterKorbanRows[0]['nama_korban'] === 'Korban A (Telah Diedit)');
report("Slot ID Korban ketiga ($thirdKid) di-reuse secara in-place untuk entitas baru (Anti-Jump)", in_array($thirdKid, $afterKIds, true) && $afterKorbanRows[2]['nama_korban'] === 'Korban Baru D (Baru Masuk)');

// Uji coba pengurangan korban (dari 3 menjadi 2 korban) untuk menguji penghapusan baris anak
$reducedData = $updateOpsData;
$reducedData['korban'] = [
    ['id_korban' => $firstKid, 'nama_korban' => 'Korban A (Telah Diedit)', 'umur' => '41', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => 'DP'],
    ['id_korban' => $secondKid, 'nama_korban' => 'Korban Asli B', 'umur' => '35', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => 'DP']
];
OperasiModel::update($testOpsId, $reducedData);

$stmtReducedK = $db->prepare("SELECT id_korban FROM Korban WHERE id_operasi = :id ORDER BY id_korban ASC");
$stmtReducedK->execute([':id' => $testOpsId]);
$reducedKIds = array_column($stmtReducedK->fetchAll(), 'id_korban');
report("Jumlah korban berkurang menjadi 2 setelah baris ketiga dihapus", count($reducedKIds) === 2);
report("ID Korban ketiga ($thirdKid) berhasil terhapus dari basis data saat entitas dikurangi", !in_array($thirdKid, $reducedKIds, true));

// Verifikasi DetailUnsur & DetailAlut diferensial
$stmtUCount = $db->prepare("SELECT COUNT(*) FROM DetailUnsur WHERE id_operasi = :id");
$stmtUCount->execute([':id' => $testOpsId]);
report("DetailUnsur bertambah menjadi 3 record", (int)$stmtUCount->fetchColumn() === 3);

$stmtACount = $db->prepare("SELECT COUNT(*) FROM DetailAlut WHERE id_operasi = :id");
$stmtACount->execute([':id' => $testOpsId]);
report("DetailAlut bertambah menjadi 2 record", (int)$stmtACount->fetchColumn() === 2);

// Verifikasi Sinkronisasi Spasial Dinamis (GIS LKP & Route Sync on Edit)
$stmtGis = $db->prepare("SELECT lat, lng, label FROM GisPoint WHERE id = :pid OR id_operasi = :id");
$stmtGis->execute([':pid' => "pt-lkp-op-{$testOpsId}", ':id' => $testOpsId]);
$gisRow = $stmtGis->fetch();
report("Layer spasial GisPoint otomatis tersinkronisasi saat koordinat diedit", !empty($gisRow));

// Verifikasi Audit Trail Pembaruan
$stmtAuditUp = $db->prepare("SELECT * FROM AuditLog WHERE target_type = 'OPERASI' AND target_id = :ref AND action = 'UPDATE_OPERASI' ORDER BY id DESC LIMIT 1");
$stmtAuditUp->execute([':ref' => "OPS-{$testOpsId}"]);
$auditUpRow = $stmtAuditUp->fetch();
report("Event UPDATE_OPERASI tercatat di tabel AuditLog", !empty($auditUpRow));

// -------------------------------------------------------------------------
// 3. Setup Dokumen RENOPS dan SITREP untuk Pengujian State Locking
// -------------------------------------------------------------------------
echo "\n--- 3. Setup RENOPS & SITREP Sebelum Pengujian Penutupan ---\n";

$renopsId = RenopsModel::create([
    'id_operasi' => $testOpsId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'waktu_briefing' => '2026-10-21 06:00:00',
    'waktu_mulai_ops' => '2026-10-21 06:30:00',
    'satuan_jarak' => 'NM',
    'srus' => [
        ['nama_sru' => 'SRU 1', 'koordinat_area' => 'Area Alfa', 'luas_area' => '10 NM²', 'pola_pencarian' => 'Parallel Track Search']
    ]
]);
report("RENOPS uji berhasil diterbitkan", $renopsId > 0, "ID Renops: {$renopsId}");

$sitrepId = LaporanModel::create([
    'id_operasi' => $testOpsId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'jam_laporan' => '16:00:00',
    'jenis_laporan' => 'HARIAN',
    'isi_laporan' => 'Pencarian hari pertama nihil, dilanjutkan esok hari.'
]);
report("SITREP Harian uji berhasil diterbitkan", $sitrepId > 0, "ID Sitrep: {$sitrepId}");

// -------------------------------------------------------------------------
// 4. Audit Transisi Status Operasi (Close Operation)
// -------------------------------------------------------------------------
echo "\n--- 4. Audit Penutupan Operasi SAR (State Transition: OPEN -> CLOSED) ---\n";

$waktuTutup = '2026-10-21 17:30:00';
$closeOk = OperasiModel::closeOperation($testOpsId, $waktuTutup);
report("OperasiModel::closeOperation berhasil dieksekusi", $closeOk === true);

$stmtCheckClose = $db->prepare("SELECT status, waktu_selesai FROM Operasi WHERE id_operasi = :id");
$stmtCheckClose->execute([':id' => $testOpsId]);
$closedOps = $stmtCheckClose->fetch();
report("Status operasi berubah menjadi 'CLOSED'", $closedOps['status'] === 'CLOSED');
report("Waktu selesai tersimpan di database", $closedOps['waktu_selesai'] === $waktuTutup);

// Verifikasi Audit Log CLOSE_OPERASI
$stmtAuditClose = $db->prepare("SELECT * FROM AuditLog WHERE target_type = 'OPERASI' AND target_id = :ref AND action = 'CLOSE_OPERASI' ORDER BY id DESC LIMIT 1");
$stmtAuditClose->execute([':ref' => "OPS-{$testOpsId}"]);
$auditCloseRow = $stmtAuditClose->fetch();
report("Event CLOSE_OPERASI tercatat di tabel AuditLog", !empty($auditCloseRow));

// -------------------------------------------------------------------------
// 5. Audit Proteksi Operasi Terkunci (Closed State Lock - Keamanan Data)
// -------------------------------------------------------------------------
echo "\n--- 5. Audit Proteksi Status Terkunci (Closed State Lock) ---\n";

// Uji coba 1: Update Laporan Awal pada Operasi CLOSED (HARUS DITOLAK)
$blockedUpdateOps = false;
try {
    OperasiModel::update($testOpsId, ['nama_operasi' => 'Mencoba Mengubah Operasi Tertutup']);
} catch (RuntimeException $e) {
    $blockedUpdateOps = true;
}
report("Update Laporan Awal DITOLAK saat operasi CLOSED", $blockedUpdateOps);

// Uji coba 2: Pembuatan RENOPS baru pada Operasi CLOSED (HARUS DITOLAK)
$blockedCreateRenops = false;
try {
    RenopsModel::create([
        'id_operasi' => $testOpsId,
        'hari_ke' => 'H.2',
        'tanggal_laporan' => '2026-10-22'
    ]);
} catch (RuntimeException $e) {
    $blockedCreateRenops = true;
}
report("Pembuatan RENOPS baru DITOLAK saat operasi CLOSED", $blockedCreateRenops);

// Uji coba 3: Pembaruan RENOPS pada Operasi CLOSED (HARUS DITOLAK)
$blockedUpdateRenops = false;
try {
    RenopsModel::update($renopsId, ['cuaca' => 'Mencoba Ubah Cuaca']);
} catch (RuntimeException $e) {
    $blockedUpdateRenops = true;
}
report("Pembaruan RENOPS DITOLAK saat operasi CLOSED", $blockedUpdateRenops);

// Uji coba 4: Pembuatan SITREP Harian pada Operasi CLOSED (HARUS DITOLAK)
$blockedCreateSitrep = false;
try {
    LaporanModel::create([
        'id_operasi' => $testOpsId,
        'hari_ke' => 'H.2',
        'jenis_laporan' => 'HARIAN',
        'isi_laporan' => 'Mencoba Tambah Lapbang'
    ]);
} catch (RuntimeException $e) {
    $blockedCreateSitrep = true;
}
report("Pembuatan SITREP harian baru DITOLAK saat operasi CLOSED", $blockedCreateSitrep);

// Uji coba 5: Pembaruan SITREP Harian pada Operasi CLOSED (HARUS DITOLAK)
$blockedUpdateSitrep = false;
try {
    LaporanModel::update($sitrepId, ['isi_laporan' => 'Mencoba Edit Lapbang']);
} catch (RuntimeException $e) {
    $blockedUpdateSitrep = true;
}
report("Pembaruan SITREP harian lama DITOLAK saat operasi CLOSED", $blockedUpdateSitrep);

// -------------------------------------------------------------------------
// 6. Audit Pembukaan Kembali Operasi (Rollback Penutupan: CLOSED -> OPEN)
// -------------------------------------------------------------------------
echo "\n--- 6. Audit Pembukaan Kembali Operasi (State Transition: CLOSED -> OPEN) ---\n";

$waktuReopen = '2026-10-22 08:00:00';
$reopenOk = OperasiModel::rollbackPenutupan($testOpsId, $waktuReopen);
report("OperasiModel::rollbackPenutupan berhasil dieksekusi", $reopenOk === true);

$stmtCheckReopen = $db->prepare("SELECT status, waktu_selesai, waktu_reopen, hari_sebelum_reopen FROM Operasi WHERE id_operasi = :id");
$stmtCheckReopen->execute([':id' => $testOpsId]);
$reopenedOps = $stmtCheckReopen->fetch();
report("Status operasi kembali menjadi 'OPEN'", $reopenedOps['status'] === 'OPEN');
report("Waktu selesai di-reset menjadi NULL", $reopenedOps['waktu_selesai'] === null);
report("Waktu reopen tersimpan di database", $reopenedOps['waktu_reopen'] === $waktuReopen);
report("Hari sebelum penutupan tercatat untuk perhitungan hari lanjutan", !empty($reopenedOps['hari_sebelum_reopen']));

// Verifikasi Audit Log REOPEN_OPERASI
$stmtAuditReopen = $db->prepare("SELECT * FROM AuditLog WHERE target_type = 'OPERASI' AND target_id = :ref AND action = 'REOPEN_OPERASI' ORDER BY id DESC LIMIT 1");
$stmtAuditReopen->execute([':ref' => "OPS-{$testOpsId}"]);
$auditReopenRow = $stmtAuditReopen->fetch();
report("Event REOPEN_OPERASI tercatat di tabel AuditLog", !empty($auditReopenRow));

// Verifikasi bahwa pasca-reopen, operasi dapat diperbarui kembali
$reopenUpdateSuccess = false;
try {
    $reopenUpdateSuccess = OperasiModel::update($testOpsId, ['nama_operasi' => 'Operasi SAR Uji Modul 3 [AKTIF KEMBALI SETELAH REOPEN]']);
} catch (Throwable) {}
report("Pembaruan data Laporan Awal kembali diizinkan pasca-pembukaan kembali", $reopenUpdateSuccess === true);

// -------------------------------------------------------------------------
// 7. Audit Pembaruan Telegram BRA SAR
// -------------------------------------------------------------------------
echo "\n--- 7. Audit Pembaruan Dokumen Telegram BRA SAR ---\n";

$braId = BraModel::create([
    'kategori' => 'OPERASI_SAR',
    'satker_kode' => 'KDI',
    'id_operasi' => $testOpsId,
    'is_auto_no_bra' => true,
    'sections' => [
        ['kode' => 'A', 'isi' => 'KECELAKAAN KAPAL AWAL'],
        ['kode' => 'B', 'isi' => '03 58 30 S / 122 36 15 E']
    ]
]);
report("Telegram BRA SAR berhasil diterbitkan", $braId > 0, "ID BRA: {$braId}");

$stmtOriginalBra = $db->prepare("SELECT no_bra FROM BraSar WHERE id_bra = :id");
$stmtOriginalBra->execute([':id' => $braId]);
$origNoBra = $stmtOriginalBra->fetchColumn();

// Update BRA SAR
$braUpdateOk = BraModel::update($braId, [
    'no_bra' => $origNoBra,
    'id_operasi' => $testOpsId,
    'kategori' => 'OPERASI_SAR',
    'sections' => [
        ['kode' => 'A', 'isi' => 'KECELAKAAN KAPAL [SEKSI A TELAH DIPERBARUI]'],
        ['kode' => 'B', 'isi' => '04 02 10 S / 122 45 00 E'],
        ['kode' => 'C', 'isi' => 'RESCUER POS AL DAN POLAIRUD [SEKSI BARU C]']
    ]
]);
report("BraModel::update berhasil dieksekusi", $braUpdateOk === true);

$stmtCheckBraSec = $db->prepare("SELECT COUNT(*) FROM BraSection WHERE id_bra = :id");
$stmtCheckBraSec->execute([':id' => $braId]);
$countBraSec = (int)$stmtCheckBraSec->fetchColumn();
report("BraSection bertambah menjadi 3 bagian", $countBraSec === 3, "Count: {$countBraSec}");

// -------------------------------------------------------------------------
// 8. Audit Pembaruan Master Data (Alut, Tim, SarUnit)
// -------------------------------------------------------------------------
echo "\n--- 8. Audit Pembaruan Master Data ---\n";

$newAlutId = MasterModel::createAlut('Alut Modul 3 Uji', 'Unit');
$upAlutOk = MasterModel::updateAlut($newAlutId, 'Alut Modul 3 [DIRENAME]', 'Paket');
report("MasterModel::updateAlut berhasil", $upAlutOk === true);

$stmtCheckAlut = $db->prepare("SELECT nama_alut, satuan FROM MasterAlut WHERE id_alut = :id");
$stmtCheckAlut->execute([':id' => $newAlutId]);
$rowAlut = $stmtCheckAlut->fetch();
report("Data Master Alut terupdate di database", $rowAlut['nama_alut'] === 'Alut Modul 3 [DIRENAME]' && $rowAlut['satuan'] === 'Paket');

$newTimId = MasterModel::createTim('Tim Modul 3 Uji');
$upTimOk = MasterModel::updateTim($newTimId, 'Tim Modul 3 [DIRENAME]');
report("MasterModel::updateTim berhasil", $upTimOk === true);

// -------------------------------------------------------------------------
// 9. Pembersihan Data Uji Coba (Teardown)
// -------------------------------------------------------------------------
echo "\n--- 9. Pembersihan Data Uji Coba (Teardown) ---\n";

if ($newAlutId > 0) MasterModel::deleteAlut($newAlutId);
if ($newTimId > 0) MasterModel::deleteTim($newTimId);
report("Master data uji coba dibersihkan", true);

if ($braId > 0) {
    $db->prepare("DELETE FROM BraSection WHERE id_bra = :id")->execute([':id' => $braId]);
    $db->prepare("DELETE FROM BraSar WHERE id_bra = :id")->execute([':id' => $braId]);
    report("BRA SAR uji coba dibersihkan", true);
}

if ($testOpsId > 0) {
    OperasiModel::delete($testOpsId);
    report("Operasi uji coba beserta seluruh relasi dibersihkan", true);
}

$dbOpsEnd = (int)$db->query("SELECT COUNT(*) FROM Operasi")->fetchColumn();
report("Jumlah total Operasi kembali sinkron ke kondisi awal", $dbOpsEnd === $dbOpsCountInitial, "Current: {$dbOpsEnd}, Initial: {$dbOpsCountInitial}");

// -------------------------------------------------------------------------
// HASIL AKHIR PENGUJIAN
// -------------------------------------------------------------------------
echo "\n=========================================================================\n";
echo "  HASIL AKHIR AUDIT MODUL 3: {$totalPassed} LULUS (PASS), {$totalFailed} GAGAL (FAIL)\n";
echo "=========================================================================\n\n";

if ($totalFailed > 0) {
    exit(1);
}
