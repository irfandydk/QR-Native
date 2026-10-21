<?php
/**
 * Test CRUD Audit End-to-End (Create, Update, Delete Database Verification)
 * Quick Report SAR System - Basarnas KPP Kendari
 */
require_once __DIR__ . '/../config/app.php';

echo "=== MEMULAI TEST AUDIT E2E SIMPAN, UPDATE & DELETE (CRUD KE DATABASE) ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(string $name, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo "![FAIL] $name: $details\n";
        $failCount++;
    }
}

$db = getDB();

// =========================================================================
// 1. AUDIT KHUSUS: SEARCH AREA RECTANGLE (10 NM² -> 15 NM²)
// =========================================================================
echo "\n--- 1. AUDIT SEARCH AREA RECTANGLE (PERSEGI 10 NM² -> 15 NM²) ---\n";

// 1.1 SIMPAN (TAMBAH) 10 NM²
$rect10Pts = [
    ['lat' => -4.0100, 'lng' => 122.5000, 'label' => 'Titik A (NW)'],
    ['lat' => -4.0100, 'lng' => 122.5834, 'label' => 'Titik B (NE)'],
    ['lat' => -4.0433, 'lng' => 122.5834, 'label' => 'Titik C (SE)'],
    ['lat' => -4.0433, 'lng' => 122.5000, 'label' => 'Titik D (SW)']
];

$polyId = GisModel::savePolygon([
    'name' => 'SEARCH AREA SRU 1',
    'shapeType' => 'rectangle',
    'centerLat' => -4.0266,
    'centerLng' => 122.5417,
    'boxWidthNm' => 5.0,
    'boxHeightNm' => 2.0,
    'areaNm2' => 10.0,
    'points' => $rect10Pts
]);

assertCondition("Search Area 10 NM² berhasil disimpan (ID: $polyId)", !empty($polyId));

// Verifikasi langsung ke Database
$row1 = $db->query("SELECT * FROM GisPolygon WHERE id = " . $db->quote($polyId))->fetch(PDO::FETCH_ASSOC);
$ptsCount1 = (int)$db->query("SELECT COUNT(*) FROM GisPolygonPoint WHERE polygonId = " . $db->quote($polyId))->fetchColumn();

assertCondition(
    "Data tersimpan di tabel GisPolygon sesuai input (Nama: {$row1['name']}, Bentuk: {$row1['shapeType']})",
    $row1 && $row1['name'] === 'SEARCH AREA SRU 1' && $row1['shapeType'] === 'rectangle'
);
assertCondition(
    "Luas di database adalah 10.00 NM² (boxWidthNm: {$row1['boxWidthNm']}, boxHeightNm: {$row1['boxHeightNm']}, areaNm2: {$row1['areaNm2']})",
    $row1 && (float)$row1['boxWidthNm'] === 5.0 && (float)$row1['boxHeightNm'] === 2.0 && (float)$row1['areaNm2'] === 10.0
);
assertCondition("4 Titik sudut (Vertices) tersimpan di GisPolygonPoint", $ptsCount1 === 4);

// 1.2 EDIT (UPDATE) MENJADI 15 NM²
$rect15Pts = [
    ['lat' => -4.0100, 'lng' => 122.5000, 'label' => 'Titik A (NW)'],
    ['lat' => -4.0100, 'lng' => 122.5834, 'label' => 'Titik B (NE)'],
    ['lat' => -4.0600, 'lng' => 122.5834, 'label' => 'Titik C (SE)'],
    ['lat' => -4.0600, 'lng' => 122.5000, 'label' => 'Titik D (SW)']
];

$updatePolyId = GisModel::savePolygon([
    'id' => $polyId,
    'name' => 'SEARCH AREA SRU 1 (EDITED 15 NM2)',
    'shapeType' => 'rectangle',
    'centerLat' => -4.0350,
    'centerLng' => 122.5417,
    'boxWidthNm' => 5.0,
    'boxHeightNm' => 3.0,
    'areaNm2' => 15.0,
    'points' => $rect15Pts
]);

assertCondition("Eksekusi Edit Search Area berhasil (ID tetap sama)", $updatePolyId === $polyId);

// Verifikasi langsung ke Database setelah Edit
$row2 = $db->query("SELECT * FROM GisPolygon WHERE id = " . $db->quote($polyId))->fetch(PDO::FETCH_ASSOC);
$ptsCount2 = (int)$db->query("SELECT COUNT(*) FROM GisPolygonPoint WHERE polygonId = " . $db->quote($polyId))->fetchColumn();

assertCondition(
    "Data ter-update di database (Nama: {$row2['name']})",
    $row2 && $row2['name'] === 'SEARCH AREA SRU 1 (EDITED 15 NM2)'
);
assertCondition(
    "Luas di database sukses diperbarui menjadi 15.00 NM² (Lebar: {$row2['boxWidthNm']}, Tinggi: {$row2['boxHeightNm']}, Luas: {$row2['areaNm2']})",
    $row2 && (float)$row2['boxWidthNm'] === 5.0 && (float)$row2['boxHeightNm'] === 3.0 && (float)$row2['areaNm2'] === 15.0
);
assertCondition("4 Titik sudut di GisPolygonPoint tetap konsisten dan ter-update", $ptsCount2 === 4);

// 1.3 HAPUS (DELETE)
$delPolyOk = GisModel::deletePolygon($polyId);
assertCondition("Eksekusi Hapus Search Area berhasil", $delPolyOk === true);

$checkRowAfterDel = (int)$db->query("SELECT COUNT(*) FROM GisPolygon WHERE id = " . $db->quote($polyId))->fetchColumn();
$checkPtsAfterDel = (int)$db->query("SELECT COUNT(*) FROM GisPolygonPoint WHERE polygonId = " . $db->quote($polyId))->fetchColumn();

assertCondition("Record GisPolygon terhapus total dari database (Count: $checkRowAfterDel)", $checkRowAfterDel === 0);
assertCondition("Record GisPolygonPoint terhapus total (Cascaded/Cleaned: $checkPtsAfterDel)", $checkPtsAfterDel === 0);

// =========================================================================
// 2. AUDIT SEARCH AREA CIRCLE (LINGKARAN)
// =========================================================================
echo "\n--- 2. AUDIT SEARCH AREA CIRCLE (LINGKARAN) ---\n";

$circleId = GisModel::savePolygon([
    'name' => 'SEARCH AREA CIRCLE UTAMA',
    'shapeType' => 'circle',
    'centerLat' => -4.0500,
    'centerLng' => 122.5000,
    'radiusNm' => 2.0,
    'areaNm2' => round(M_PI * 4, 2),
    'points' => [
        ['lat' => -4.05, 'lng' => 122.50, 'label' => 'R1'],
        ['lat' => -4.06, 'lng' => 122.51, 'label' => 'R2'],
        ['lat' => -4.04, 'lng' => 122.49, 'label' => 'R3']
    ]
]);

$circleRow = $db->query("SELECT * FROM GisPolygon WHERE id = " . $db->quote($circleId))->fetch(PDO::FETCH_ASSOC);
assertCondition(
    "Circle berhasil disimpan dengan Radius 2 NM dan Luas " . round(M_PI * 4, 2) . " NM²",
    $circleRow && (float)$circleRow['radiusNm'] === 2.0 && (float)$circleRow['areaNm2'] === round(M_PI * 4, 2)
);

// Edit Circle
GisModel::savePolygon([
    'id' => $circleId,
    'name' => 'SEARCH AREA CIRCLE PERLUASAN',
    'shapeType' => 'circle',
    'centerLat' => -4.0500,
    'centerLng' => 122.5000,
    'radiusNm' => 3.0,
    'areaNm2' => round(M_PI * 9, 2),
    'points' => [
        ['lat' => -4.05, 'lng' => 122.50, 'label' => 'R1'],
        ['lat' => -4.06, 'lng' => 122.51, 'label' => 'R2'],
        ['lat' => -4.04, 'lng' => 122.49, 'label' => 'R3']
    ]
]);

$circleRow2 = $db->query("SELECT * FROM GisPolygon WHERE id = " . $db->quote($circleId))->fetch(PDO::FETCH_ASSOC);
assertCondition(
    "Circle berhasil di-update dengan Radius 3 NM dan Luas " . round(M_PI * 9, 2) . " NM²",
    $circleRow2 && (float)$circleRow2['radiusNm'] === 3.0 && (float)$circleRow2['areaNm2'] === round(M_PI * 9, 2)
);

GisModel::deletePolygon($circleId);
$circleCount = (int)$db->query("SELECT COUNT(*) FROM GisPolygon WHERE id = " . $db->quote($circleId))->fetchColumn();
assertCondition("Circle berhasil dihapus dari database", $circleCount === 0);

// =========================================================================
// 3. AUDIT TITIK SAR (GIS POINT)
// =========================================================================
echo "\n--- 3. AUDIT TITIK SAR (GIS POINT) ---\n";

$ptId = GisModel::savePoint([
    'name' => 'POSKO TAKTIS RANOMEETO',
    'lat' => -4.0841,
    'lng' => 122.4283,
    'category' => 'POSKO_SAR',
    'description' => 'Posko Lapangan Utama'
]);

$ptRow = $db->query("SELECT * FROM GisPoint WHERE id = " . $db->quote($ptId))->fetch(PDO::FETCH_ASSOC);
assertCondition("Titik SAR tersimpan (Kategori: {$ptRow['category']}, Label: {$ptRow['label']})", $ptRow && $ptRow['category'] === 'POSKO_SAR');

// Update Titik SAR
GisModel::savePoint([
    'id' => $ptId,
    'label' => 'DERMAGA SAR BASARNAS',
    'lat' => -4.0850,
    'lng' => 122.4300,
    'category' => 'DERMAGA_SAR',
    'description' => 'Dermaga sandar RB 210'
]);

$ptRow2 = $db->query("SELECT * FROM GisPoint WHERE id = " . $db->quote($ptId))->fetch(PDO::FETCH_ASSOC);
assertCondition("Titik SAR ter-update (Kategori: {$ptRow2['category']}, Label: {$ptRow2['label']})", $ptRow2 && $ptRow2['category'] === 'DERMAGA_SAR');

// Delete Titik SAR
$delPt = GisModel::deletePoint($ptId);
$ptCount = (int)$db->query("SELECT COUNT(*) FROM GisPoint WHERE id = " . $db->quote($ptId))->fetchColumn();
assertCondition("Titik SAR terhapus dari database", $delPt && $ptCount === 0);

// =========================================================================
// 4. AUDIT POLA PENCARIAN (GIS SEARCH PATTERN)
// =========================================================================
echo "\n--- 4. AUDIT POLA PENCARIAN (GIS SEARCH PATTERN) ---\n";

$patId = GisModel::saveSearchPattern([
    'name' => 'PARALLEL TRACK SRU 1',
    'polygonId' => 'dummy-poly',
    'polygonName' => 'Area Sektor A',
    'patternType' => 'parallel_track',
    'trackSpacingNm' => 1.5,
    'legsCount' => 6,
    'poskoId' => 'posko-1',
    'poskoName' => 'Kansar Kendari',
    'color' => '#f97316',
    'totalDistanceNm' => 24.5,
    'createdAt' => date('Y-m-d H:i:s')
]);

$patRow = $db->query("SELECT * FROM GisSearchPattern WHERE id = " . $db->quote($patId))->fetch(PDO::FETCH_ASSOC);
assertCondition("Pola Pencarian tersimpan (Tipe: {$patRow['patternType']}, Spasi: {$patRow['trackSpacingNm']} NM)", $patRow && $patRow['patternType'] === 'parallel_track');

// Update Pattern
GisModel::saveSearchPattern([
    'id' => $patId,
    'name' => 'CREEPING LINE SRU 1 UPDATED',
    'polygonId' => 'dummy-poly',
    'polygonName' => 'Area Sektor A',
    'patternType' => 'creeping_line',
    'trackSpacingNm' => 2.0,
    'legsCount' => 8,
    'poskoId' => 'posko-1',
    'poskoName' => 'Kansar Kendari',
    'color' => '#10b981',
    'totalDistanceNm' => 32.0,
    'createdAt' => date('Y-m-d H:i:s')
]);

$patRow2 = $db->query("SELECT * FROM GisSearchPattern WHERE id = " . $db->quote($patId))->fetch(PDO::FETCH_ASSOC);
assertCondition("Pola Pencarian ter-update (Tipe: {$patRow2['patternType']}, Spasi: {$patRow2['trackSpacingNm']} NM)", $patRow2 && $patRow2['patternType'] === 'creeping_line' && (float)$patRow2['trackSpacingNm'] === 2.0);

GisModel::deleteSearchPattern($patId);
$patCount = (int)$db->query("SELECT COUNT(*) FROM GisSearchPattern WHERE id = " . $db->quote($patId))->fetchColumn();
assertCondition("Pola Pencarian terhapus dari database", $patCount === 0);

// =========================================================================
// 5. AUDIT JALUR / RUTE (GIS ROUTE)
// =========================================================================
echo "\n--- 5. AUDIT JALUR / RUTE (GIS ROUTE) ---\n";

$routeId = GisModel::saveRoute([
    'name' => 'RUTE PENYISIRAN PANTAI',
    'color' => '#3b82f6',
    'waypoints' => [
        ['lat' => -4.01, 'lng' => 122.50, 'label' => 'WP 1'],
        ['lat' => -4.03, 'lng' => 122.55, 'label' => 'WP 2']
    ]
]);

$routeRow = $db->query("SELECT * FROM GisRoute WHERE id = " . $db->quote($routeId))->fetch(PDO::FETCH_ASSOC);
$routeWpCount = (int)$db->query("SELECT COUNT(*) FROM GisRoutePoint WHERE routeId = " . $db->quote($routeId))->fetchColumn();
assertCondition("Rute tersimpan di GisRoute & 2 Waypoints di GisRoutePoint", $routeRow && $routeWpCount === 2);

// Update Route (tambah 1 waypoint)
GisModel::saveRoute([
    'id' => $routeId,
    'name' => 'RUTE PENYISIRAN DIPERPANJANG',
    'color' => '#ef4444',
    'waypoints' => [
        ['lat' => -4.01, 'lng' => 122.50, 'label' => 'WP 1'],
        ['lat' => -4.03, 'lng' => 122.55, 'label' => 'WP 2'],
        ['lat' => -4.05, 'lng' => 122.60, 'label' => 'WP 3']
    ]
]);

$routeRow2 = $db->query("SELECT * FROM GisRoute WHERE id = " . $db->quote($routeId))->fetch(PDO::FETCH_ASSOC);
$routeWpCount2 = (int)$db->query("SELECT COUNT(*) FROM GisRoutePoint WHERE routeId = " . $db->quote($routeId))->fetchColumn();
assertCondition("Rute ter-update di database & Waypoint bertambah menjadi 3", $routeRow2['name'] === 'RUTE PENYISIRAN DIPERPANJANG' && $routeWpCount2 === 3);

GisModel::deleteRoute($routeId);
$routeCountAfter = (int)$db->query("SELECT COUNT(*) FROM GisRoute WHERE id = " . $db->quote($routeId))->fetchColumn();
$routeWpAfter = (int)$db->query("SELECT COUNT(*) FROM GisRoutePoint WHERE routeId = " . $db->quote($routeId))->fetchColumn();
assertCondition("Rute dan seluruh Waypoint terhapus total dari database", $routeCountAfter === 0 && $routeWpAfter === 0);

// =========================================================================
// 6. AUDIT OPERASI SAR CRUD
// =========================================================================
echo "\n--- 6. AUDIT OPERASI SAR CRUD ---\n";

$opsId = OperasiModel::create([
    'nama_operasi' => 'OPERASI SAR KECELAKAAN KAPAL KM UJI 01',
    'jenis_kejadian' => 'Kecelakaan Kapal',
    'lokasi_kejadian' => 'Perairan Teluk Kendari',
    'koordinat' => '03° 58\' 30" S - 122° 35\' 12" E',
    'waktu_kejadian' => date('Y-m-d H:i:s'),
    'jumlah_pob' => 5,
    'status' => 'OPEN',
    'kronologi' => 'Kapal mati mesin di teluk Kendari dengan POB 5 orang.'
]);

assertCondition("Operasi SAR berhasil dibuat (ID: $opsId)", $opsId > 0);
$opsRow = OperasiModel::getById($opsId);
assertCondition(
    "Data Operasi tersimpan di DB (Nama: {$opsRow['nama_operasi']}, Status: {$opsRow['status']})",
    $opsRow && $opsRow['nama_operasi'] === 'OPERASI SAR KECELAKAAN KAPAL KM UJI 01' && $opsRow['status'] === 'OPEN'
);

// Update Operasi SAR
$updOps = OperasiModel::update($opsId, [
    'nama_operasi' => 'OPERASI SAR KM UJI 01 (REVISI DATA)',
    'jenis_kejadian' => 'Kecelakaan Kapal',
    'lokasi_kejadian' => 'Perairan Wakatobi',
    'koordinat' => '05° 19\' 00" S - 123° 35\' 00" E',
    'waktu_kejadian' => date('Y-m-d H:i:s'),
    'jumlah_pob' => 7,
    'status' => 'OPEN',
    'kronologi' => 'Informasi terverifikasi POB 7 orang di perairan Wakatobi.'
]);

assertCondition("Operasi SAR berhasil di-update", $updOps === true);
$opsRow2 = OperasiModel::getById($opsId);
assertCondition(
    "Data revisi tersimpan di DB (Nama: {$opsRow2['nama_operasi']}, POB: {$opsRow2['jumlah_pob']}, Lokasi: {$opsRow2['lokasi_kejadian']})",
    $opsRow2 && $opsRow2['jumlah_pob'] == 7 && $opsRow2['lokasi_kejadian'] === 'Perairan Wakatobi'
);

// =========================================================================
// 7. AUDIT RENOPS (RENCANA OPERASI) CRUD
// =========================================================================
echo "\n--- 7. AUDIT RENOPS (RENCANA OPERASI) CRUD ---\n";

$renopsId = RenopsModel::create([
    'id_operasi' => $opsId,
    'hari_ke' => '1',
    'tanggal_laporan' => date('Y-m-d'),
    'waktu_briefing' => '07:00:00',
    'satuan_jarak' => 'NM',
    'deskripsi' => 'Rencana pencarian hari pertama SRU Laut dan Darat.'
]);

assertCondition("Renops berhasil dibuat (ID: $renopsId)", $renopsId > 0);
$renopsRow = RenopsModel::getById($renopsId);
assertCondition(
    "Data Renops tersimpan di DB (Hari ke: {$renopsRow['hari_ke']}, Satuan: {$renopsRow['satuan_jarak']})",
    $renopsRow && ($renopsRow['hari_ke'] === 'H.1' || (int)$renopsRow['hari_ke'] === 1) && $renopsRow['satuan_jarak'] === 'NM'
);

// Update Renops
$updRenops = RenopsModel::update($renopsId, [
    'id_operasi' => $opsId,
    'hari_ke' => '1',
    'tanggal_laporan' => date('Y-m-d'),
    'waktu_briefing' => '07:30:00',
    'satuan_jarak' => 'KM',
    'deskripsi' => 'Rencana pencarian hari pertama diperluas 15 KM.'
]);

assertCondition("Renops berhasil di-update", $updRenops === true);
$renopsRow2 = RenopsModel::getById($renopsId);
assertCondition(
    "Data Renops ter-update di DB (Waktu: {$renopsRow2['waktu_briefing']}, Satuan: {$renopsRow2['satuan_jarak']})",
    $renopsRow2 && $renopsRow2['satuan_jarak'] === 'KM'
);

// Delete Renops
$delRenops = RenopsModel::delete($renopsId);
assertCondition("Renops berhasil dihapus dari database", $delRenops === true && RenopsModel::getById($renopsId) === null);

// =========================================================================
// 8. AUDIT SITREP (LAPORAN HARIAN) CRUD
// =========================================================================
echo "\n--- 8. AUDIT SITREP (LAPORAN HARIAN) CRUD ---\n";

$sitrepId = LaporanModel::create([
    'id_operasi' => $opsId,
    'hari_ke' => '1',
    'tanggal_laporan' => date('Y-m-d'),
    'waktu_laporan' => date('Y-m-d H:i:s'),
    'isi_laporan' => 'Pencarian hari ke-1 hasil nihil.',
    'jenis_laporan' => 'PERKEMBANGAN'
]);

assertCondition("SITREP berhasil dibuat (ID: $sitrepId)", $sitrepId > 0);
$sitrepRow = LaporanModel::getById($sitrepId);
assertCondition("SITREP tersimpan di DB (Isi: {$sitrepRow['isi_laporan']})", $sitrepRow && str_contains($sitrepRow['isi_laporan'], 'nihil'));

// Update SITREP
$updSitrep = LaporanModel::update($sitrepId, [
    'id_operasi' => $opsId,
    'hari_ke' => '1',
    'tanggal_laporan' => date('Y-m-d'),
    'waktu_laporan' => date('Y-m-d H:i:s'),
    'isi_laporan' => 'Ditemukan 2 korban selamat terdampar.',
    'jenis_laporan' => 'PERKEMBANGAN'
]);

assertCondition("SITREP berhasil di-update", $updSitrep === true);
$sitrepRow2 = LaporanModel::getById($sitrepId);
assertCondition(
    "SITREP ter-update di DB (Isi: {$sitrepRow2['isi_laporan']})",
    $sitrepRow2 && str_contains($sitrepRow2['isi_laporan'], 'terdampar')
);

$delSitrep = LaporanModel::delete($sitrepId);
assertCondition("SITREP berhasil dihapus dari database", $delSitrep && LaporanModel::getById($sitrepId) === null);

// Delete Operasi SAR Uji
$delOps = OperasiModel::delete($opsId);
assertCondition("Operasi SAR berhasil dihapus dari database", $delOps && OperasiModel::getById($opsId) === null);

// =========================================================================
// 9. AUDIT BRA SAR CRUD
// =========================================================================
echo "\n--- 9. AUDIT BRA SAR CRUD ---\n";

$braId = BraModel::create([
    'kategori' => 'TW',
    'no_bra' => 'BRA-SAR/01/TEST/2026',
    'is_auto_no_bra' => false,
    'klasifikasi' => 'KILAT',
    'derajat' => 'BIASA',
    'kepada' => 'KABASARNAS',
    'dari' => 'KANSAR KENDARI',
    'perihal' => 'LAPORAN KECELAKAAN KAPAL',
    'tanggal_waktu' => date('Y-m-d H:i:s'),
    'lokasi' => 'Perairan Buton',
    'sections' => [
        ['kode' => 'AAA', 'isi' => 'UJI COBA INTEGRITAS DATABASE BRA SAR']
    ]
]);

assertCondition("BRA SAR berhasil dibuat (ID: $braId)", $braId > 0);
$braRow = BraModel::getById($braId);
assertCondition("BRA tersimpan di DB (Nomor: {$braRow['no_bra']})", $braRow && str_contains($braRow['no_bra'], 'BRA-SAR/01/TEST'));

// Update BRA
$updBra = BraModel::update($braId, [
    'kategori' => 'TW',
    'no_bra' => 'BRA-SAR/01/TEST-REV/2026',
    'klasifikasi' => 'KILAT',
    'derajat' => 'BIASA',
    'kepada' => 'KABASARNAS',
    'dari' => 'KANSAR KENDARI',
    'perihal' => 'LAPORAN PERKEMBANGAN SAR',
    'tanggal_waktu' => date('Y-m-d H:i:s'),
    'lokasi' => 'Perairan Buton Utara',
    'sections' => [
        ['kode' => 'AAA', 'isi' => 'UJI COBA REVISI DATA BRA SAR']
    ]
]);

assertCondition("BRA SAR berhasil di-update", $updBra === true);
$braRow2 = BraModel::getById($braId);
assertCondition("BRA ter-update di DB (Nomor: {$braRow2['no_bra']})", $braRow2 && str_contains($braRow2['no_bra'], 'TEST-REV'));

$delBra = BraModel::delete($braId);
assertCondition("BRA SAR berhasil dihapus dari database", $delBra && BraModel::getById($braId) === null);

// =========================================================================
// 10. AUDIT MASTER DATA (ALUT, TIM, PEJABAT) CRUD
// =========================================================================
echo "\n--- 10. AUDIT MASTER DATA (ALUT, TIM, PEJABAT) CRUD ---\n";

// 10.1 Master Alut
$alutId = MasterModel::createAlut(['nama_alut' => 'KN SAR PACITAN TEST', 'satuan' => 'Unit']);
assertCondition("Master Alut tersimpan (ID: $alutId)", $alutId > 0);
$checkAlut = $db->query("SELECT * FROM MasterAlut WHERE id_alut = $alutId")->fetch();
assertCondition("Data Alut ada di DB (Nama: {$checkAlut['nama_alut']})", $checkAlut && $checkAlut['nama_alut'] === 'KN SAR PACITAN TEST');

MasterModel::updateAlut($alutId, ['nama_alut' => 'KN SAR PACITAN 240', 'satuan' => 'Kapal']);
$checkAlut2 = $db->query("SELECT * FROM MasterAlut WHERE id_alut = $alutId")->fetch();
assertCondition("Data Alut ter-update (Nama: {$checkAlut2['nama_alut']}, Satuan: {$checkAlut2['satuan']})", $checkAlut2['nama_alut'] === 'KN SAR PACITAN 240' && $checkAlut2['satuan'] === 'Kapal');

MasterModel::deleteAlut($alutId);
$countAlut = (int)$db->query("SELECT COUNT(*) FROM MasterAlut WHERE id_alut = $alutId")->fetchColumn();
assertCondition("Data Alut berhasil dihapus dari DB", $countAlut === 0);

// 10.2 Master Tim
$timId = MasterModel::createTim(['nama_tim' => 'POS SAR KOLAKA TEST']);
assertCondition("Master Tim tersimpan (ID: $timId)", $timId > 0);
MasterModel::updateTim($timId, ['nama_tim' => 'TIM RESCUE POS SAR KOLAKA']);
$checkTim = $db->query("SELECT * FROM MasterTim WHERE id_tim = $timId")->fetch();
assertCondition("Data Tim ter-update di DB (Nama: {$checkTim['nama_tim']})", $checkTim['nama_tim'] === 'TIM RESCUE POS SAR KOLAKA');

MasterModel::deleteTim($timId);
$countTim = (int)$db->query("SELECT COUNT(*) FROM MasterTim WHERE id_tim = $timId")->fetchColumn();
assertCondition("Data Tim berhasil dihapus dari DB", $countTim === 0);

// 10.3 Master Pejabat
$pejId = MasterModel::createPejabat([
    'nama' => 'DRS. H. AMIRUDDIN, M.SI',
    'nip' => '197501012000031001',
    'pangkat' => 'PEMBINA UTAMA MUDA',
    'jabatan' => 'KEPALA SEKSI OPERASI',
    'is_default_smc' => 0
]);
assertCondition("Master Pejabat tersimpan (ID: $pejId)", !empty($pejId));
$checkPej = $db->query("SELECT * FROM Pejabat WHERE id = " . $db->quote($pejId))->fetch();
assertCondition("Data Pejabat ada di DB (Nama: {$checkPej['nama']})", $checkPej && $checkPej['nama'] === 'DRS. H. AMIRUDDIN, M.SI');

MasterModel::updatePejabat($pejId, [
    'nama' => 'DRS. H. AMIRUDDIN, M.SI (KASIOPS)',
    'nip' => '197501012000031001',
    'pangkat' => 'PEMBINA TINGKAT I',
    'jabatan' => 'KEPALA SEKSI OPERASI DAN SIAGA',
    'is_default_smc' => 0
]);
$checkPej2 = $db->query("SELECT * FROM Pejabat WHERE id = " . $db->quote($pejId))->fetch();
assertCondition("Data Pejabat ter-update di DB (Jabatan: {$checkPej2['jabatan']})", $checkPej2['jabatan'] === 'KEPALA SEKSI OPERASI DAN SIAGA');

MasterModel::deletePejabat($pejId);
$countPej = (int)$db->query("SELECT COUNT(*) FROM Pejabat WHERE id = " . $db->quote($pejId))->fetchColumn();
assertCondition("Data Pejabat berhasil dihapus dari DB", $countPej === 0);

// =========================================================================
// RINGKASAN TEST
// =========================================================================
echo "\n=======================================================\n";
echo "HASIL AKHIR AUDIT END-TO-END CRUD KE BASIS DATA:\n";
echo "Total Berhasil (PASS): $passCount\n";
echo "Total Gagal (FAIL)   : $failCount\n";
echo "=======================================================\n";

if ($failCount === 0) {
    echo "\n✓ SEMUA OPERASI SIMPAN, UPDATE & DELETE TERBUKTI 100% SUKSES DAN DATA PERSISTEN KONSISTEN DENGAN DATABASE!\n";
    exit(0);
} else {
    echo "\n✗ TERDAPAT OPERASI CRUD YANG GAGAL. SILAKAN CEK LOG DI ATAS.\n";
    exit(1);
}
