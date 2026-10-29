<?php
/**
 * QR-SAR - Automated Audit & E2E Test Suite: MODUL 4 (Siklus Penghapusan Data & Integritas Relasional)
 * KPP Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../helpers/BraHelper.php';
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
echo "  AUDIT E2E MODUL 4: SIKLUS PENGHAPUSAN (DELETE/CASCADE) & INTEGRITAS RELASI \n";
echo "  KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS      \n";
echo "=========================================================================\n\n";

$db = Database::getConnection();
$dbOpsCountInitial = (int)$db->query("SELECT COUNT(*) FROM Operasi")->fetchColumn();

// -------------------------------------------------------------------------
// 1. Audit Otorisasi RBAC & Metode Akses Penghapusan
// -------------------------------------------------------------------------
echo "--- 1. Audit Otorisasi RBAC & Metode Akses Penghapusan ---\n";

// Skenario A: Operator Biasa (Role USER)
$userOperator = [
    'id' => 'USR-TEST-OP',
    'username' => 'operator_sar',
    'role' => 'USER'
];
AuthHelper::setSession($userOperator);
report("Operator biasa (USER) DITOLAK menghapus Operasi", AuthHelper::canDelete('operasi') === false);
report("Operator biasa (USER) DITOLAK menghapus Renops", AuthHelper::canDelete('renops') === false);
report("Operator biasa (USER) DITOLAK menghapus Master Data", AuthHelper::canDelete('master') === false);

// Skenario B: Administrator (Role ADMIN)
$userAdmin = [
    'id' => 'USR-TEST-ADM',
    'username' => 'admin_sar',
    'role' => 'ADMIN'
];
AuthHelper::setSession($userAdmin);
report("Administrator (ADMIN) DIIZINKAN menghapus Operasi", AuthHelper::canDelete('operasi') === true);
report("Administrator (ADMIN) DIIZINKAN menghapus Master Data", AuthHelper::canDelete('master') === true);

// -------------------------------------------------------------------------
// 2. Setup Operasi Komprehensif dengan Seluruh 8 Layer Dependensi Relasional
// -------------------------------------------------------------------------
echo "\n--- 2. Setup Operasi Komprehensif (8 Layer Dependensi Relasional) ---\n";

$fullOpsData = [
    'nama_operasi' => 'Operasi SAR Uji Modul 4 Cascade & Relational Integrity',
    'nama_pelapor' => 'Bapak Komandan Pos',
    'lokasi_kejadian' => 'Perairan Selat Buton',
    'koordinat' => "04° 40' 00.00\" S - 122° 40' 00.00\" E",
    'waktu_kejadian' => '2026-10-21 06:00:00',
    'waktu_lapor' => '2026-10-21 06:30:00',
    'waktu_berangkat' => '2026-10-21 06:45:00',
    'eta_tiba' => '2026-10-21 08:00:00',
    'jumlah_pob' => 2,
    'korban' => [
        ['nama_korban' => 'Korban Test 1', 'umur' => '30', 'jk' => 'Laki-laki', 'alamat' => 'Baubau', 'status_korban' => 'DP'],
        ['nama_korban' => 'Korban Test 2', 'umur' => '25', 'jk' => 'Laki-laki', 'alamat' => 'Baubau', 'status_korban' => 'DP']
    ],
    'detail_unsur' => [
        ['nama_unsur' => 'Pos SAR Baubau', 'jumlah_personil' => 4],
        ['nama_unsur' => 'Polairud Baubau', 'jumlah_personil' => 2]
    ],
    'detail_alut' => [
        ['id_alut' => 1, 'nama_alut' => 'RIB 01 Baubau', 'satuan' => 'Unit', 'jumlah_alut' => 1]
    ],
    'detail_tim_berangkat' => [
        ['id_tim' => 1, 'nama_tim' => 'Tim Rescue Baubau']
    ]
];

$testOpsId = OperasiModel::create($fullOpsData);
report("Operasi induk uji coba berhasil diterbitkan", $testOpsId > 0, "ID: {$testOpsId}");

// Layer 1 & 2: Renops & RenopsSRU
$renopsId = RenopsModel::create([
    'id_operasi' => $testOpsId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'srus' => [
        ['nama_sru' => 'SRU 1 Baubau', 'koordinat_area' => 'Area 1', 'luas_area' => '15 NM²', 'pola_pencarian' => 'Parallel Track Search'],
        ['nama_sru' => 'SRU 2 Baubau', 'koordinat_area' => 'Area 2', 'luas_area' => '10 NM²', 'pola_pencarian' => 'Creeping Line Search']
    ]
]);
report("Layer 1 & 2: RENOPS beserta 2 SRU berhasil ditautkan", $renopsId > 0, "ID Renops: {$renopsId}");

// Layer 3: Laporan Harian (SITREP)
$sitrepId = LaporanModel::create([
    'id_operasi' => $testOpsId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'jenis_laporan' => 'HARIAN',
    'isi_laporan' => 'Pencarian masih berlangsung.'
]);
report("Layer 3: Laporan Harian (SITREP) berhasil ditautkan", $sitrepId > 0, "ID Sitrep: {$sitrepId}");

// Layer 4: Telegram Berita SAR (BraSar & BraSection)
$braId = BraModel::create([
    'kategori' => 'OPERASI_SAR',
    'satker_kode' => 'KDI',
    'id_operasi' => $testOpsId,
    'is_auto_no_bra' => true,
    'sections' => [
        ['kode' => 'A', 'isi' => 'KECELAKAAN KAPAL SELAT BUTON'],
        ['kode' => 'B', 'isi' => '04 40 00 S / 122 40 00 E']
    ]
]);
report("Layer 4: Telegram BRA SAR berhasil ditautkan ke Operasi", $braId > 0, "ID BRA: {$braId}");

// Layer 5: GIS Polygon & PolygonPoints
$polyId = GisModel::savePolygon([
    'id' => "poly-test-{$testOpsId}",
    'id_operasi' => $testOpsId,
    'name' => 'Search Area Uji Selat Buton',
    'shapeType' => 'polygon',
    'points' => [
        ['lat' => -4.66, 'lng' => 122.66, 'label' => 'A'],
        ['lat' => -4.66, 'lng' => 122.76, 'label' => 'B'],
        ['lat' => -4.76, 'lng' => 122.76, 'label' => 'C'],
        ['lat' => -4.76, 'lng' => 122.66, 'label' => 'D']
    ]
]);
report("Layer 5: GIS Polygon beserta 4 PolygonPoint berhasil ditautkan", !empty($polyId));

// Layer 6: GIS Route & RoutePoints
$routeId = GisModel::saveRoute([
    'id' => "route-test-{$testOpsId}",
    'id_operasi' => $testOpsId,
    'name' => 'Rute Pencarian SRU Baubau',
    'points' => [
        ['lat' => -4.66, 'lng' => 122.66, 'label' => 'WP-1'],
        ['lat' => -4.70, 'lng' => 122.70, 'label' => 'WP-2']
    ]
]);
report("Layer 6: GIS Route beserta 2 RoutePoint berhasil ditautkan", !empty($routeId));

// Layer 7: GIS Search Pattern & Waypoints
$patternId = GisModel::saveSearchPattern([
    'id' => "pattern-test-{$testOpsId}",
    'id_operasi' => $testOpsId,
    'name' => 'Pola Parallel Search Selat Buton',
    'patternType' => 'parallel_track',
    'waypoints' => [
        ['lat' => -4.66, 'lng' => 122.66, 'label' => 'Leg 1'],
        ['lat' => -4.68, 'lng' => 122.68, 'label' => 'Leg 2']
    ]
]);
report("Layer 7: GIS Search Pattern beserta 2 Waypoint berhasil ditautkan", !empty($patternId));

// Layer 8: GisPoint (LKP)
$stmtCheckPt = $db->prepare("SELECT id FROM GisPoint WHERE id = :pid OR id_operasi = :id");
$stmtCheckPt->execute([':pid' => "pt-lkp-op-{$testOpsId}", ':id' => $testOpsId]);
$ptRow = $stmtCheckPt->fetch();
report("Layer 8: GIS LKP Point terhubung ke Operasi", !empty($ptRow));

// -------------------------------------------------------------------------
// 3. Eksekusi Penghapusan Operasi (Cascading Deletion)
// -------------------------------------------------------------------------
echo "\n--- 3. Eksekusi Penghapusan Bertingkat (Cascading Delete) ---\n";

$deleteResult = OperasiModel::delete($testOpsId);
report("OperasiModel::delete berhasil dieksekusi tanpa melanggar FK", $deleteResult === true);

// -------------------------------------------------------------------------
// 4. Audit Penanganan Khusus Telegram BRA SAR (Soft-Detach)
// -------------------------------------------------------------------------
echo "\n--- 4. Audit Soft-Detach Telegram BRA SAR (Preservasi Arsip Dinas) ---\n";

$stmtCheckBra = $db->prepare("SELECT id_bra, id_operasi, no_bra FROM BraSar WHERE id_bra = :id");
$stmtCheckBra->execute([':id' => $braId]);
$braPostDelete = $stmtCheckBra->fetch();

report("Record Telegram BRA SAR TIDAK terhapus dari basis data", !empty($braPostDelete));
report("Relasi id_operasi pada BRA SAR berubah menjadi NULL (Soft-detached)", $braPostDelete['id_operasi'] === null);

$stmtCheckBraSec = $db->prepare("SELECT COUNT(*) FROM BraSection WHERE id_bra = :id");
$stmtCheckBraSec->execute([':id' => $braId]);
$secCountPostDelete = (int)$stmtCheckBraSec->fetchColumn();
report("Seluruh butir seksi BraSection tetap utuh", $secCountPostDelete === 2);

// -------------------------------------------------------------------------
// 5. Verifikasi Pembersihan Utuh Seluruh 7 Layer Anak
// -------------------------------------------------------------------------
echo "\n--- 5. Verifikasi Pembersihan Bersih (Zero Residuals pada 7 Layer Anak) ---\n";

$cOps = (int)$db->query("SELECT COUNT(*) FROM Operasi WHERE id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel induk Operasi bersih (0 record)", $cOps === 0);

$cRenSru = (int)$db->query("SELECT COUNT(*) FROM RenopsSRU WHERE id_renops = {$renopsId}")->fetchColumn();
report("Tabel RenopsSRU bersih (0 record)", $cRenSru === 0);

$cRen = (int)$db->query("SELECT COUNT(*) FROM Renops WHERE id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel Renops bersih (0 record)", $cRen === 0);

$cSit = (int)$db->query("SELECT COUNT(*) FROM LaporanHarian WHERE id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel LaporanHarian bersih (0 record)", $cSit === 0);

$cKorb = (int)$db->query("SELECT COUNT(*) FROM Korban WHERE id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel Korban bersih (0 record)", $cKorb === 0);

$cUnsur = (int)$db->query("SELECT COUNT(*) FROM DetailUnsur WHERE id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel DetailUnsur bersih (0 record)", $cUnsur === 0);

$cAlut = (int)$db->query("SELECT COUNT(*) FROM DetailAlut WHERE id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel DetailAlut bersih (0 record)", $cAlut === 0);

$cTim = (int)$db->query("SELECT COUNT(*) FROM DetailTimBerangkat WHERE id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel DetailTimBerangkat bersih (0 record)", $cTim === 0);

$cPolyPt = (int)$db->query("SELECT COUNT(*) FROM GisPolygonPoint WHERE polygonId = 'poly-test-{$testOpsId}'")->fetchColumn();
report("Tabel GisPolygonPoint bersih (0 record)", $cPolyPt === 0);

$cPoly = (int)$db->query("SELECT COUNT(*) FROM GisPolygon WHERE id = 'poly-test-{$testOpsId}' OR id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel GisPolygon bersih (0 record)", $cPoly === 0);

$cRoutePt = (int)$db->query("SELECT COUNT(*) FROM GisRoutePoint WHERE routeId = 'route-test-{$testOpsId}'")->fetchColumn();
report("Tabel GisRoutePoint bersih (0 record)", $cRoutePt === 0);

$cRoute = (int)$db->query("SELECT COUNT(*) FROM GisRoute WHERE id = 'route-test-{$testOpsId}' OR id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel GisRoute bersih (0 record)", $cRoute === 0);

$cPatWp = (int)$db->query("SELECT COUNT(*) FROM GisSearchPatternWaypoint WHERE searchPatternId = 'pattern-test-{$testOpsId}'")->fetchColumn();
report("Tabel GisSearchPatternWaypoint bersih (0 record)", $cPatWp === 0);

$cPat = (int)$db->query("SELECT COUNT(*) FROM GisSearchPattern WHERE id = 'pattern-test-{$testOpsId}' OR id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel GisSearchPattern bersih (0 record)", $cPat === 0);

$cPt = (int)$db->query("SELECT COUNT(*) FROM GisPoint WHERE id = 'pt-lkp-op-{$testOpsId}' OR id_operasi = {$testOpsId}")->fetchColumn();
report("Tabel GisPoint bersih (0 record)", $cPt === 0);

// -------------------------------------------------------------------------
// 6. Audit Integritas Global: Zero-Orphan Across Entire Database
// -------------------------------------------------------------------------
echo "\n--- 6. Audit Integritas Global (Pemeriksaan Zero-Orphan Seluruh Database) ---\n";

$orphanKorban = (int)$db->query("SELECT COUNT(*) FROM Korban WHERE id_operasi NOT IN (SELECT id_operasi FROM Operasi)")->fetchColumn();
report("0 Record Korban Orphan", $orphanKorban === 0, "Count: {$orphanKorban}");

$orphanUnsur = (int)$db->query("SELECT COUNT(*) FROM DetailUnsur WHERE id_operasi NOT IN (SELECT id_operasi FROM Operasi)")->fetchColumn();
report("0 Record DetailUnsur Orphan", $orphanUnsur === 0, "Count: {$orphanUnsur}");

$orphanAlut = (int)$db->query("SELECT COUNT(*) FROM DetailAlut WHERE id_operasi NOT IN (SELECT id_operasi FROM Operasi)")->fetchColumn();
report("0 Record DetailAlut Orphan", $orphanAlut === 0, "Count: {$orphanAlut}");

$orphanRenops = (int)$db->query("SELECT COUNT(*) FROM Renops WHERE id_operasi NOT IN (SELECT id_operasi FROM Operasi)")->fetchColumn();
report("0 Record Renops Orphan", $orphanRenops === 0, "Count: {$orphanRenops}");

$orphanSru = (int)$db->query("SELECT COUNT(*) FROM RenopsSRU WHERE id_renops NOT IN (SELECT id_renops FROM Renops)")->fetchColumn();
report("0 Record RenopsSRU Orphan", $orphanSru === 0, "Count: {$orphanSru}");

$orphanSitrep = (int)$db->query("SELECT COUNT(*) FROM LaporanHarian WHERE id_operasi NOT IN (SELECT id_operasi FROM Operasi)")->fetchColumn();
report("0 Record LaporanHarian Orphan", $orphanSitrep === 0, "Count: {$orphanSitrep}");

$orphanPolyPt = (int)$db->query("SELECT COUNT(*) FROM GisPolygonPoint WHERE polygonId NOT IN (SELECT id FROM GisPolygon)")->fetchColumn();
report("0 Record GisPolygonPoint Orphan", $orphanPolyPt === 0, "Count: {$orphanPolyPt}");

$orphanRoutePt = (int)$db->query("SELECT COUNT(*) FROM GisRoutePoint WHERE routeId NOT IN (SELECT id FROM GisRoute)")->fetchColumn();
report("0 Record GisRoutePoint Orphan", $orphanRoutePt === 0, "Count: {$orphanRoutePt}");

$orphanWp = (int)$db->query("SELECT COUNT(*) FROM GisSearchPatternWaypoint WHERE searchPatternId NOT IN (SELECT id FROM GisSearchPattern)")->fetchColumn();
report("0 Record GisSearchPatternWaypoint Orphan", $orphanWp === 0, "Count: {$orphanWp}");

// -------------------------------------------------------------------------
// 7. Audit Penghapusan Mandiri (Sub-Entity Standalone Delete)
// -------------------------------------------------------------------------
echo "\n--- 7. Audit Penghapusan Entitas Mandiri (Standalone Deletion) ---\n";

// Standalone Renops Delete
$standaloneOpsId = OperasiModel::create([
    'nama_operasi' => 'Operasi Standalone Sub-Delete Test',
    'nama_pelapor' => 'Pelapor Standalone',
    'lokasi_kejadian' => 'Lokasi Standalone',
    'waktu_kejadian' => '2026-10-21 09:00:00'
]);

$standRenId = RenopsModel::create([
    'id_operasi' => $standaloneOpsId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'srus' => [['nama_sru' => 'SRU Standalone', 'koordinat_area' => 'Area X', 'luas_area' => '5 NM²']]
]);
report("RENOPS mandiri berhasil dibuat", $standRenId > 0);

$delRenOk = RenopsModel::delete($standRenId);
report("RenopsModel::delete berhasil dieksekusi", $delRenOk === true);
$cCheckSru = (int)$db->query("SELECT COUNT(*) FROM RenopsSRU WHERE id_renops = {$standRenId}")->fetchColumn();
report("RenopsSRU miliknya otomatis terhapus", $cCheckSru === 0);

// Operasi induk tetap hidup
$cCheckOpsParent = (int)$db->query("SELECT COUNT(*) FROM Operasi WHERE id_operasi = {$standaloneOpsId}")->fetchColumn();
report("Operasi induk tetap aman dan tidak terhapus saat Renops mandiri dihapus", $cCheckOpsParent === 1);

// Standalone SITREP Delete
$standSitId = LaporanModel::create([
    'id_operasi' => $standaloneOpsId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'jenis_laporan' => 'HARIAN',
    'isi_laporan' => 'Lapbang mandiri'
]);
report("SITREP mandiri berhasil dibuat", $standSitId > 0);

$delSitOk = LaporanModel::delete($standSitId);
report("LaporanModel::delete berhasil dieksekusi", $delSitOk === true);
$cCheckSit = (int)$db->query("SELECT COUNT(*) FROM LaporanHarian WHERE id_laporan = {$standSitId}")->fetchColumn();
report("SITREP berhasil terhapus dari basis data", $cCheckSit === 0);

// Standalone GIS Polygon Delete
$standPolyId = GisModel::savePolygon([
    'id' => "poly-standalone-test",
    'name' => 'Polygon Standalone',
    'shapeType' => 'polygon',
    'points' => [
        ['lat' => -4.5, 'lng' => 122.5, 'label' => 'A'],
        ['lat' => -4.6, 'lng' => 122.6, 'label' => 'B']
    ]
]);
report("GIS Polygon mandiri berhasil dibuat", !empty($standPolyId));

$delPolyOk = GisModel::deletePolygon($standPolyId);
report("GisModel::deletePolygon berhasil dieksekusi tanpa galat FK", $delPolyOk === true);
$cCheckPolyPt = (int)$db->query("SELECT COUNT(*) FROM GisPolygonPoint WHERE polygonId = '{$standPolyId}'")->fetchColumn();
report("GisPolygonPoint miliknya otomatis bersih", $cCheckPolyPt === 0);

// Hapus Operasi standalone uji
OperasiModel::delete($standaloneOpsId);

// -------------------------------------------------------------------------
// 8. Verifikasi Audit Log DELETE_OPERASI
// -------------------------------------------------------------------------
echo "\n--- 8. Verifikasi Pencatatan Audit Trail Penghapusan ---\n";

$stmtAuditDel = $db->prepare("SELECT * FROM AuditLog WHERE target_type = 'OPERASI' AND target_id = :ref AND action = 'DELETE_OPERASI' ORDER BY id DESC LIMIT 1");
$stmtAuditDel->execute([':ref' => "OPS-{$testOpsId}"]);
$auditDelRow = $stmtAuditDel->fetch();
report("Event DELETE_OPERASI tercatat di tabel AuditLog", !empty($auditDelRow) && $auditDelRow['action'] === 'DELETE_OPERASI');

// -------------------------------------------------------------------------
// 9. Pembersihan Akhir (Teardown)
// -------------------------------------------------------------------------
echo "\n--- 9. Pembersihan Akhir Data Uji (Teardown) ---\n";

if ($braId > 0) {
    $db->prepare("DELETE FROM BraSection WHERE id_bra = :id")->execute([':id' => $braId]);
    $db->prepare("DELETE FROM BraSar WHERE id_bra = :id")->execute([':id' => $braId]);
    report("Sisa arsip BRA SAR uji dibersihkan dari basis data", true);
}

$dbOpsEnd = (int)$db->query("SELECT COUNT(*) FROM Operasi")->fetchColumn();
report("Jumlah total Operasi kembali sinkron ke kondisi awal", $dbOpsEnd === $dbOpsCountInitial, "Current: {$dbOpsEnd}, Initial: {$dbOpsCountInitial}");

// -------------------------------------------------------------------------
// HASIL AKHIR PENGUJIAN
// -------------------------------------------------------------------------
echo "\n=========================================================================\n";
echo "  HASIL AKHIR AUDIT MODUL 4: {$totalPassed} LULUS (PASS), {$totalFailed} GAGAL (FAIL)\n";
echo "=========================================================================\n\n";

if ($totalFailed > 0) {
    exit(1);
}
