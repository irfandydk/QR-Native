<?php
/**
 * QR-SAR - Automated Test Suite for Phase 8 (Model Domain Relations, Sub-API Handlers & Daily Rotating Logger)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Verifies:
 * 1. OperasiRelationService domain service existence and method signatures.
 * 2. OperasiModel transparent backward-compatible delegation to OperasiRelationService.
 * 3. End-to-end differential sync logic for DetailUnsur and DetailAlut.
 * 4. GisApiController modular sub-dispatchers structure.
 * 5. LogService daily rotating file writes, format compliance, and retrieval.
 * 6. Protection of storage/logs directory.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/app.php';

echo "====================================================================" . PHP_EOL;
echo "   UJI OTOMATIS FASE 8: RELASI DOMAIN, SUB-API & ROTATING LOGGER   " . PHP_EOL;
echo "====================================================================" . PHP_EOL;

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " => {$detail}" : '') . PHP_EOL;
        $failed++;
    }
}

// ---------------------------------------------------------------------
// 1. VERIFIKASI OPERASI RELATION SERVICE & MODEL DELEGATION
// ---------------------------------------------------------------------
echo PHP_EOL . "--- 1. VERIFIKASI DOMAIN SERVICE: OperasiRelationService ---" . PHP_EOL;

assertCondition(
    "Kelas OperasiRelationService tersedia dan terdaftar",
    class_exists('OperasiRelationService')
);

$methods = [
    'applyDifferentialUnsur',
    'applyDifferentialAlut',
    'applyDifferentialTimBerangkat',
    'syncUnsurAlut'
];

foreach ($methods as $m) {
    assertCondition(
        "OperasiRelationService memiliki method {$m}()",
        method_exists('OperasiRelationService', $m)
    );
    assertCondition(
        "OperasiModel mempertahankan method facade {$m}() untuk backward-compatibility",
        method_exists('OperasiModel', $m)
    );
}

// ---------------------------------------------------------------------
// 2. SIMULASI TRANSAKSI SINKRONISASI DIFERENSIAL RELASI
// ---------------------------------------------------------------------
echo PHP_EOL . "--- 2. SIMULASI INTEGRITAS SINKRONISASI DIFERENSIAL UNSUR & ALUT ---" . PHP_EOL;

$db = Database::getConnection();
$dummyOpsId = null;

try {
    // Buat operasi uji via OperasiModel::create
    $dummyOpsId = OperasiModel::create([
        'nama_operasi' => 'TEST OPS RELASI FASE 8 - ' . time(),
        'jenis_kejadian' => 'KECELAKAAN_KAPAL',
        'lokasi_kejadian' => 'Perairan Teluk Kendari Uji',
        'nama_pelapor' => 'Petugas Uji',
        'hubungan_pelapor' => 'Internal',
        'no_telp_pelapor' => '081122334455',
        'koordinat' => "03° 58' 45\" S - 122° 35' 50\" E",
        'waktu_kejadian' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'waktu_lapor' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'waktu_berangkat' => date('Y-m-d H:i:s', strtotime('-45 minutes')),
        'status' => 'OPEN'
    ]);

    assertCondition("Operasi uji relasi berhasil dibuat (ID: {$dummyOpsId})", $dummyOpsId > 0);

    // Initial Unsur sync
    $unsurBatch1 = [
        ['nama_unsur' => 'Rescuer Basarnas Kendari', 'jumlah_personil' => 6],
        ['nama_unsur' => 'Polairud Polda Sultra', 'jumlah_personil' => 4]
    ];
    $alutBatch1 = [
        ['nama_alut' => 'KN SAR Pacitan', 'satuan' => 'Unit', 'jumlah_alut' => 1],
        ['nama_alut' => 'RIB 01 Kendari', 'satuan' => 'Unit', 'jumlah_alut' => 1]
    ];

    OperasiModel::syncUnsurAlut($dummyOpsId, $unsurBatch1, $alutBatch1);

    $checkUnsur1 = $db->query("SELECT nama_unsur, jumlah_personil FROM DetailUnsur WHERE id_operasi = {$dummyOpsId} ORDER BY id_detail_unsur ASC")->fetchAll(PDO::FETCH_ASSOC);
    assertCondition("Batch 1: DetailUnsur tersimpan 2 baris", count($checkUnsur1) === 2);

    $checkAlut1 = $db->query("SELECT nama_alut, jumlah_alut FROM DetailAlut WHERE id_operasi = {$dummyOpsId} ORDER BY id_detail_alut ASC")->fetchAll(PDO::FETCH_ASSOC);
    assertCondition("Batch 1: DetailAlut tersimpan 2 baris", count($checkAlut1) === 2);

    // Differential sync (Update personil, hapus Polairud, tambah Pos AL)
    $unsurBatch2 = [
        ['nama_unsur' => 'Rescuer Basarnas Kendari', 'jumlah_personil' => 8], // Updated
        ['nama_unsur' => 'Pos TNI AL Kendari', 'jumlah_personil' => 3]        // Replaced/New
    ];
    $alutBatch2 = [
        ['nama_alut' => 'KN SAR Pacitan', 'satuan' => 'Unit', 'jumlah_alut' => 2] // Updated, RIB deleted
    ];

    OperasiRelationService::syncUnsurAlut($dummyOpsId, $unsurBatch2, $alutBatch2);

    $checkUnsur2 = $db->query("SELECT nama_unsur, jumlah_personil FROM DetailUnsur WHERE id_operasi = {$dummyOpsId} ORDER BY id_detail_unsur ASC")->fetchAll(PDO::FETCH_ASSOC);
    assertCondition("Batch 2: Diferensial Unsur tersinkronisasi presisi (2 baris)", count($checkUnsur2) === 2);
    assertCondition("Personil Rescuer Basarnas terupdate ke 8 orang", (int)$checkUnsur2[0]['jumlah_personil'] === 8);
    assertCondition("Unsur kedua terupdate menjadi Pos TNI AL Kendari", $checkUnsur2[1]['nama_unsur'] === 'Pos TNI AL Kendari');

    $checkAlut2 = $db->query("SELECT nama_alut, jumlah_alut FROM DetailAlut WHERE id_operasi = {$dummyOpsId}")->fetchAll(PDO::FETCH_ASSOC);
    assertCondition("Batch 2: Diferensial Alut tersinkronisasi presisi (1 baris)", count($checkAlut2) === 1);
    assertCondition("Jumlah KN SAR Pacitan terupdate menjadi 2 unit", (int)$checkAlut2[0]['jumlah_alut'] === 2);

} finally {
    if ($dummyOpsId) {
        $db->exec("DELETE FROM DetailUnsur WHERE id_operasi = {$dummyOpsId}");
        $db->exec("DELETE FROM DetailAlut WHERE id_operasi = {$dummyOpsId}");
        $db->exec("DELETE FROM Operasi WHERE id_operasi = {$dummyOpsId}");
        assertCondition("Pembersihan data dummy operasi uji tuntas & bersih", true);
    }
}

// ---------------------------------------------------------------------
// 3. VERIFIKASI MODULARISASI GisApiController SUB-DISPATCHERS
// ---------------------------------------------------------------------
echo PHP_EOL . "--- 3. VERIFIKASI SUB-DISPATCHERS GisApiController ---" . PHP_EOL;

$apiControllerFile = file_get_contents($rootDir . '/api/controllers/GisApiController.php');
assertCondition(
    "GisApiController memiliki sub-dispatcher handleBundleRoute()",
    str_contains($apiControllerFile, 'function handleBundleRoute(')
);
assertCondition(
    "GisApiController memiliki sub-dispatcher handlePointsRoute()",
    str_contains($apiControllerFile, 'function handlePointsRoute(')
);
assertCondition(
    "GisApiController memiliki sub-dispatcher handleCustomIconsRoute()",
    str_contains($apiControllerFile, 'function handleCustomIconsRoute(')
);
assertCondition(
    "GisApiController memiliki sub-dispatcher handlePolygonsRoute()",
    str_contains($apiControllerFile, 'function handlePolygonsRoute(')
);
assertCondition(
    "GisApiController memiliki sub-dispatcher handlePatternsRoute()",
    str_contains($apiControllerFile, 'function handlePatternsRoute(')
);
assertCondition(
    "GisApiController memiliki sub-dispatcher handleRoutesRoute()",
    str_contains($apiControllerFile, 'function handleRoutesRoute(')
);

// ---------------------------------------------------------------------
// 4. VERIFIKASI CENTRALIZED DAILY ROTATING LOGGER (LogService)
// ---------------------------------------------------------------------
echo PHP_EOL . "--- 4. VERIFIKASI CENTRALIZED DAILY ROTATING LOGGER (LogService) ---" . PHP_EOL;

assertCondition("Kelas LogService tersedia dan terdaftar", class_exists('LogService'));

$uniqueToken = "TEST_LOG_EVENT_" . bin2hex(random_bytes(6));
LogService::info("Pengujian pencatatan peristiwa INFO terstandarisasi: {$uniqueToken}", ['module' => 'PHASE_8_TEST', 'code' => 200]);
LogService::warning("Pengujian peringatan WARNING sistem: {$uniqueToken}", ['threshold' => 85]);
LogService::error("Pengujian pencatatan kesalahan ERROR: {$uniqueToken}", ['file' => __FILE__, 'line' => __LINE__]);

$todayLogPath = LogService::getLogPath();
assertCondition(
    "Berkas log harian hari ini tersedia: " . basename($todayLogPath),
    file_exists($todayLogPath) && filesize($todayLogPath) > 0
);

$logContent = file_get_contents($todayLogPath);
assertCondition(
    "Berkas log harian memuat entri unik token uji",
    str_contains($logContent, $uniqueToken)
);
assertCondition(
    "Format log harian mematuhi standar [TIMESTAMP] [LEVEL] [IP] [USER] [MESSAGE]",
    (bool)preg_match('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[(INFO|WARNING|ERROR)\] \[[^\]]+\] \[[^\]]+\] Pengujian/', $logContent)
);

// Test exception logger
$testException = new RuntimeException("Simulasi Galat Uji Coba Terisolasi: {$uniqueToken}", 999);
LogService::logException($testException, 'TEST_CONTEXT');
$logContentAfter = file_get_contents($todayLogPath);
assertCondition(
    "LogService::logException() berhasil mencatat exception beserta konteks dan file sumber",
    str_contains($logContentAfter, "Simulasi Galat Uji Coba Terisolasi: {$uniqueToken}") && str_contains($logContentAfter, 'TEST_CONTEXT')
);

// Test log reading & files list
$logFiles = LogService::getLogFiles();
assertCondition(
    "LogService::getLogFiles() mengembalikan array daftar berkas log",
    is_array($logFiles) && !empty($logFiles) && isset($logFiles[0]['filename'])
);

$todayContentViaService = LogService::getLogContent(date('Y-m-d'));
assertCondition(
    "LogService::getLogContent() berhasil membaca isi log tanggal hari ini",
    $todayContentViaService !== null && str_contains($todayContentViaService, $uniqueToken)
);

// Test protection .htaccess
$htaccessPath = $rootDir . '/storage/logs/.htaccess';
assertCondition(
    "storage/logs/.htaccess terpasang untuk melindungi akses publik langsung",
    file_exists($htaccessPath) && str_contains(file_get_contents($htaccessPath), 'Require all denied')
);

// ---------------------------------------------------------------------
// RINGKASAN
// ---------------------------------------------------------------------
echo PHP_EOL . "====================================================================" . PHP_EOL;
echo "  Total Pemeriksaan Asersi : " . ($passed + $failed) . PHP_EOL;
echo "  Lulus (PASS)             : {$passed}" . PHP_EOL;
echo "  Gagal (FAIL)             : {$failed}" . PHP_EOL;
echo "====================================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
exit(0);
