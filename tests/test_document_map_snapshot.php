<?php
/**
 * Test Suite: Fitur Capture Peta Taktis 16:9 untuk Dokumen Cetak Resmi
 * (Laporan Awal, RENOPS, SITREP/Lapbang, dan Berita Acara Penutupan)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/DokumenPetaSnapshotModel.php';

$passed = 0;
$failed = 0;

function assertTrue(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[PASS] {$msg}\n";
    } else {
        $failed++;
        echo "[FAIL] {$msg}\n";
    }
}

echo "=== TEST SUITE: DOCUMENT MAP SNAPSHOT 16:9 & COMPONENT TABLE ===\n\n";

$rootDir = realpath(__DIR__ . '/..');

// 1. Lint Check seluruh file terkait
$filesToLint = [
    'models/DokumenPetaSnapshotModel.php',
    'api/controllers/GisApiController.php',
    'views/gis.php',
    'views/operasi/partials/tab_cetak.php',
    'views/operasi/partials/tab_gis.php',
    'views/operasi/partials/renops_smeac_doc.php',
    'views/operasi/renops_smeac_print.php',
];

foreach ($filesToLint as $relPath) {
    $absPath = $rootDir . '/' . $relPath;
    $out = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($absPath) . ' 2>&1', $out, $code);
    assertTrue($code === 0, "PHP Lint Valid: {$relPath}");
}

// 2. Uji Database Schema & CRUD DokumenPetaSnapshotModel
DokumenPetaSnapshotModel::ensureSchemaUpdated();

$pdo = getDB();
$stmtCheck = $pdo->query("SHOW TABLES LIKE 'DokumenPetaSnapshot'");
assertTrue((bool)$stmtCheck->fetchColumn(), "Tabel DokumenPetaSnapshot tersedia di database");

// Buat gambar PNG valid (> 64 bytes)
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$dummyPngBase64 = 'data:image/png;base64,' . base64_encode($pngBytes);

$testIdOperasi = 999991;
$pdo->prepare("DELETE FROM DokumenPetaSnapshot WHERE id_operasi = ?")->execute([$testIdOperasi]);

$sampleKomponen = [
    'unit' => 'NM',
    'points' => [
        [
            'category' => 'LKP (Last Known Position)',
            'label' => 'LKP KM Harapan Jaya',
            'dms' => '03° 58\' 12.00" S - 122° 35\' 44.00" E',
            'description' => 'Titik kontak terakhir kapal'
        ],
        [
            'category' => 'Lokasi Penemuan Korban',
            'label' => 'Penemuan Korban #1 (La Ode Ahmad)',
            'dms' => '03° 59\' 05.00" S - 122° 36\' 10.00" E',
            'description' => 'Terevakuasi oleh KN SAR Pacitan'
        ]
    ],
    'polygons' => [
        [
            'shapeType' => 'RECTANGLE',
            'name' => 'Search Area H.1 (4 Corner)',
            'area_text' => '24.50 NM² (84.03 KM²)',
            'coords_summary' => 'A: 03°55\'S 122°30\'E | B: 03°55\'S 122°40\'E | C: 04°02\'S 122°40\'E | D: 04°02\'S 122°30\'E'
        ]
    ],
    'patterns' => [
        [
            'type_label' => 'Parallel Sweep',
            'name' => 'Parallel Sweep SRU-1',
            'spacing_text' => '0.50 NM',
            'length_text' => '48.20 NM',
            'swept_area_text' => '24.10 NM²',
            'csp_dms' => 'CSP: 03° 56\' 00.00" S - 122° 31\' 00.00" E'
        ]
    ],
    'routes' => [
        [
            'name' => 'Rute Intercept KN SAR Pacitan',
            'distance_text' => '14.25 NM (26.39 KM)',
            'bearing_text' => '112.5°',
            'speed_text' => '15.0 Kts',
            'waypoints_summary' => '2 Titik Waypoint'
        ]
    ]
];

$sampleBounds = [
    'north' => -3.90,
    'south' => -4.08,
    'east' => 122.70,
    'west' => 122.48,
    'zoom' => 12,
    'unit' => 'NM'
];

// Peta Lokasi (LKP): 1x per operasi — id_referensi selalu NULL walau dikirim
$savedLkp = DokumenPetaSnapshotModel::saveSnapshot([
    'id_operasi' => $testIdOperasi,
    'jenis_dokumen' => 'RENOPS',
    'id_referensi' => 999888,
    'image_data' => $dummyPngBase64,
    'komponen' => $sampleKomponen,
    'bounds' => $sampleBounds,
    'keterangan_tambahan' => 'Peta Lokasi LKP'
]);
assertTrue(!empty($savedLkp) && (int)($savedLkp['id_operasi'] ?? 0) === $testIdOperasi && ($savedLkp['jenis_dokumen'] ?? '') === 'RENOPS', "saveSnapshot menyimpan Peta LKP (RENOPS)");
assertTrue($savedLkp['id_referensi'] === null, "Peta LKP disimpan 1x per operasi (id_referensi NULL)");
assertTrue(is_array($savedLkp['komponen']['points'] ?? null) && count($savedLkp['komponen']['points']) === 2, "JSON komponen peta mendekode 2 titik taktis");
assertTrue(($savedLkp['bounds']['unit'] ?? '') === 'NM', "JSON bounds peta menyimpan satuan aktif (NM)");
assertTrue(!array_key_exists('image_data_uri', $savedLkp), "Snapshot tidak lagi membawa data URI base64 (tidak terpakai)");

// Peta Rencana Operasi: per hari RENOPS
$savedRencana = DokumenPetaSnapshotModel::saveSnapshot([
    'id_operasi' => $testIdOperasi,
    'jenis_dokumen' => 'RENOPS_RENCANA',
    'id_referensi' => 999888,
    'image_data' => $dummyPngBase64,
]);
$absFile = $rootDir . '/' . $savedRencana['image_path'];
assertTrue((int)($savedRencana['id_referensi'] ?? 0) === 999888 && is_file($absFile), "Peta Rencana Operasi tersimpan per hari (id_referensi=999888) + berkas PNG");
assertTrue((int)(DokumenPetaSnapshotModel::getSnapshot($testIdOperasi, 'RENOPS_RENCANA', 999888)['id_snapshot'] ?? 0) === (int)$savedRencana['id_snapshot'], "getSnapshot Peta Rencana per hari");
assertTrue(DokumenPetaSnapshotModel::getSnapshot($testIdOperasi, 'RENOPS_RENCANA', 999889) === null, "Peta Rencana hari lain tidak mewarisi");
assertTrue((int)(DokumenPetaSnapshotModel::getSnapshot($testIdOperasi, 'RENOPS', 999889)['id_snapshot'] ?? 0) === (int)$savedLkp['id_snapshot'], "Peta LKP operasi berlaku untuk semua hari");
assertTrue(DokumenPetaSnapshotModel::getSnapshot($testIdOperasi, 'LAPORAN_AWAL') === null, "Jenis lama (LAPORAN_AWAL) tidak dikenal");

// Uji getAllByOperasi
$allSnaps = DokumenPetaSnapshotModel::getAllByOperasi($testIdOperasi);
assertTrue(count($allSnaps) >= 1, "getAllByOperasi mengembalikan daftar snapshot milik operasi");

// Uji Delete Snapshot & pembersihan file fisik
$del = DokumenPetaSnapshotModel::deleteSnapshot((int)$savedRencana['id_snapshot']);
DokumenPetaSnapshotModel::deleteSnapshot((int)$savedLkp['id_snapshot']);
assertTrue($del, "deleteSnapshot berhasil menghapus record dari database");
assertTrue(!is_file($absFile), "deleteSnapshot ikut membersihkan file PNG fisik dari disk");

// 3. Verifikasi Integrasi di views/gis.php & assets/js/gis_master.js
$gisPhp = file_get_contents($rootDir . '/views/gis.php');
assertTrue(strpos($gisPhp, 'id="btnToggleCaptureMode"') !== false, "views/gis.php memiliki tombol #btnToggleCaptureMode di header");
assertTrue(strpos($gisPhp, 'id="captureFrameOverlay"') !== false && strpos($gisPhp, 'id="captureFrameBox"') !== false, "views/gis.php memiliki overlay bingkai 16:9 (#captureFrameOverlay & #captureFrameBox)");
assertTrue(strpos($gisPhp, 'id="captureControlPanel"') !== false && strpos($gisPhp, 'id="btnExecuteCaptureSave"') !== false, "views/gis.php memiliki panel kontrol Studio Capture 16:9 (#captureControlPanel)");
assertTrue(strpos($gisPhp, 'value="Lokasi Penemuan Korban"') !== false, "views/gis.php menyediakan kategori titik 'Lokasi Penemuan Korban'");

$gisJs = file_get_contents($rootDir . '/assets/js/gis_master.js');
assertTrue(strpos($gisJs, 'function initDocumentCaptureUI()') !== false, "assets/js/gis_master.js mendefinisikan initDocumentCaptureUI()");
assertTrue(strpos($gisJs, 'function detectComponentsInCaptureBox(') !== false, "assets/js/gis_master.js mendefinisikan fungsi deteksi spasial BBox detectComponentsInCaptureBox()");
assertTrue(strpos($gisJs, 'function renderCaptureCanvas16x9(') !== false, "assets/js/gis_master.js mendefinisikan fungsi kanvas 16:9 renderCaptureCanvas16x9()");
assertTrue(strpos($gisJs, 'CANVAS_W = 1280') !== false && strpos($gisJs, 'CANVAS_H = 720') !== false, "renderCaptureCanvas16x9() menggunakan resolusi 16:9 presisi (1280x720)");

// 4. Integrasi peta di Dokumen RENOPS: Peta LKP (1x) & Peta Rencana Operasi (per hari)
$docPartial = file_get_contents($rootDir . '/views/operasi/partials/renops_smeac_doc.php');
assertTrue(strpos($docPartial, "capture_doc=RENOPS';") !== false && strpos($docPartial, 'capture_doc=RENOPS_RENCANA&capture_ref=') !== false,
    "Dokumen RENOPS memiliki tombol capture Peta LKP (tanpa hari) & Peta Rencana Operasi (per hari)");
assertTrue(!file_exists($rootDir . '/views/operasi/partials/print_map_snapshot_block.php'), "Partial blok peta dokumen lama sudah dihapus");

echo "\n--------------------------------------------------\n";
echo "Total Pengujian: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
echo "Result: {$passed}/" . ($passed + $failed) . " Passed\n";

exit($failed === 0 ? 0 : 1);
